<?php

namespace Buckyball\Core\Util;

use Buckyball\Core\Proto\Cls;

class Email extends Cls
{

    /**
     * @var array
     */
    static protected $_handlers = [];
    /**
     * @var string
     */
    static protected $_defaultHandler = 'default';

    /**
     *
     */
    public function __construct()
    {
        $this->addHandler('default', [$this, 'defaultHandler']);
    }

    /**
     * @param $name
     * @param $params
     */
    public function addHandler($name, $params)
    {
        if (is_callable($params)) {
            $params = [
                'description' => $name,
                'callback' => $params,
            ];
        }
        static::$_handlers[$name] = $params;
    }

    /**
     * @return array
     */
    public function getHandlers()
    {
        return static::$_handlers;
    }

    /**
     * @param $name
     */
    public function setDefaultHandler($name)
    {
        static::$_defaultHandler = $name;
    }

    /**
     * @param array $data
     * @return bool|mixed
     */
    public function send(array $data)
    {
        static $allowedHeadersRegex = '/^(to|from|cc|bcc|reply-to|return-path|content-type|list-unsubscribe|x-.*)$/';

        $data = array_change_key_case($data, CASE_LOWER);

        $body = trim($data['body']);
        unset($data['body']);

        $to      = '';
        $subject = '';
        $headers = [];
        $params  = [];
        $files   = [];

        foreach ($data as $k => $v) {
            if ($k == 'subject') {
                if ($this->BConfig->get('staging/email_subject_prepend')) {
                    $subject = $this->BConfig->get('staging/email_subject_prepend_prefix') . ' ' . $v;
                    $headers['x-staging-original-subject'] = 'X-Staging-Original-Subject: ' . $v;
                } else {
                    $subject = $v;
                }

            } elseif ($k == 'to') {
                if ($this->BConfig->get('staging/email_to_override')) {
                    $to = $this->BConfig->get('staging/email_to_override_address');
                    $headers['x-staging-original-to'] = 'X-Staging-Original-To: ' . $v;
                } else {
                    $to = $v;
                }

            } elseif ($k == 'attach') {
                foreach ((array)$v as $file) {
                    $files[] = $file;
                }

            } elseif ($k[0] === '-') {
                $params[$k] = $k . ' ' . $v;

            } elseif (preg_match($allowedHeadersRegex, $k)) {
                if (!empty($v) && $v !== '"" <>') {
                    $headers[$k] = $k . ': ' . $v;
                }
            }
        }

        $origBody = $body;

        $altBody = null;
        $isHtml = !empty($headers['content-type']) && preg_match('#text/html#', $headers['content-type']);
        $this->_formatAlternative($headers, $body, $altBody, $isHtml);

        $body = trim(preg_replace('#<!--.*?-->#', '', $body));//strip comments

        if (empty($headers['content-type'])) {
            $headers['content-type'] = 'Content-Type: text/plain; charset=utf-8';
        }

        if ($files) {
            // $body and $headers will be updated
            $this->_addAttachments($files, $headers, $body);
        }

        if (!empty($headers['bcc'])) { // workaround some weird bug in php send()
            $bcc = $headers['bcc'];
            unset($headers['bcc']);
            $headers['bcc'] = $bcc;
        }

        $emailData = [
            'to' => &$to,
            'subject' => &$subject,
            'orig_body' => &$origBody,
            'alt_body' => &$altBody,
            'is_html' => &$isHtml,
            'body' => &$body,
            'headers' => &$headers,
            'params' => &$params,
            'files' => &$files,
            'orig_data' => $data,
        ];

        return $this->_dispatch($emailData);
    }

    /**
     * @param $emailData
     * @return bool|mixed
     */
    protected function _dispatch($emailData)
    {
        try {
            $flags = $this->BEvents->fire('BEmail::send:before', ['email_data' => $emailData]);
            if ($flags === false) {
                return false;
            } elseif (is_array($flags)) {
                foreach ($flags as $f) {
                    if ($f === false) {
                        return false;
                    }
                }
            }
        } catch (BException $e) {
            BDebug::warning($e->getMessage());
            return false;
        }

        $callback = static::$_handlers[static::$_defaultHandler]['callback'];
        if (is_callable($callback)) {
            $result = $this->BUtil->call($callback, $emailData);
        } else {
            BDebug::warning('Default email handler is not callable');
            $result = false;
        }
        $emailData['result'] = $result;

        $this->BEvents->fire('BEmail::send:after', ['email_data' => $emailData]);

        return $result;
    }

    /**
     * @param $headers
     * @param $body
     * @return bool
     */
    protected function _formatAlternative(&$headers, &$body, &$altBody, &$isHtml)
    {
        if (!preg_match('#<!--=+-->#', $body)) {
            return $body;
        }
        $mimeBoundary = "==Multipart_Boundary_x" . md5(microtime()) . "x";

        // headers for attachment
        $headers['mime-version'] = "MIME-Version: 1.0";
        $headers['content-type'] = "Content-Type: multipart/alternative; boundary=\"{$mimeBoundary}\"";

        $parts = preg_split('#<!--=+-->#', $body);
        $message = "--{$mimeBoundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n" . trim($parts[0]);
        $message .= "\r\n--{$mimeBoundary}\r\nContent-Type: text/html; charset=utf-8\r\n\r\n" . trim($parts[1]);
        $message .= "\r\n--{$mimeBoundary}--";

        $altBody = $parts[0];
        $isHtml = true;

        $body = $message;
        return true;
    }

    /**
     * Add email attachment
     *
     * @param $files
     * @param $mailheaders
     * @param $body
     */
    protected function _addAttachments($files, &$headers, &$body)
    {
        $body = trim($body);

        $mimeBoundary = "==Multipart_Boundary_x" . md5(microtime()) . "x";

        //headers and message for text
        $message = "--{$mimeBoundary}\r\n{$headers['content-type']}\r\n\r\n{$body}\r\n\r\n";

        // headers for attachment
        $headers['mime-version'] = "MIME-Version: 1.0";
        $headers['content-type'] = "Content-Type: multipart/mixed; boundary=\"{$mimeBoundary}\"";

        // preparing attachments
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = chunk_split(base64_encode(file_get_contents($file)));
                $name = basename($file);
                $message .= "--{$mimeBoundary}\r\n" .
                    "Content-Type: application/octet-stream; name=\"{$name}\"\r\n" .
                    "Content-Description: {$name}\r\n" .
                    "Content-Disposition: attachment; filename=\"{$name}\"; size=" . filesize($file) . ";\r\n" .
                    "Content-Transfer-Encoding: base64\r\n\r\n{$data}\r\n\r\n";
            }
        }
        $message .= "--{$mimeBoundary}--";

        $body = $message;
        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function defaultHandler(array $data)
    {
        if (isset($data['headers']) && is_array($data['headers'])) {
            $data['headers'] = implode("\r\n", $data['headers']);
        }
        if (isset($data['params']) && is_array($data['params'])) {
            $data['params'] = implode(' ', $data['params']);
        }
        return mail(
            $data['to'],
            $data['subject'],
            $data['body'],
            $data['headers'],
            $data['params']
        );
    }
}