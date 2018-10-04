<?php

namespace Buckyball\Core\Util;

use Buckyball\Core\Data\Struct;
use Buckyball\Core\Proto\Cls;

class Http extends Cls
{

    /**
     * @var
     */
    static protected $_lastRemoteHttpInfo;
    /**
     * Send simple POST request to external server and retrieve response
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return string
     */
    public function call($method, $url, $data = [], $headers = [], $options = [])
    {
        $options['timeout'] = $options['timeout'] ?? 5;
        $options['useragent'] = $options['useragent'] ?? 'Mozilla/5.0';
        $useCurl = $options['curl'] ?? true;
        if (preg_match('#^//#', $url)) {
            $url = 'http:' . $url;
        }
        if ($method === 'GET' && $data) {
            if (is_array($data)) {
                $request = http_build_query($data, '', '&');
            } else {
                $request = $data;
            }

            $url .= (strpos($url, '?') === false ? '?' : '&') . $request;
        }
        $bus = [
            'request' => [
                'method' => $method,
                'url' => $url,
                'data' => $data,
                'headers' => $headers,
                'options' => $options,
                'multipart' => false,
                'content_type' => null,
                'post_content' => null,
            ],
            'response' => [
                'body' => null,
                'headers' => null,
                'meta' => null,
            ],
        ];

        $this->processMultipart($bus);

        $headers = $this->mergeHttpHeaders([[
            'Expect' => '', //Fixes the HTTP/1.1 417 Expectation Failed
            'Referer' => $this->app()->env()->getCurrentUrl(),
            'Accept' => '*/*',
            'Content-Type' => $contentType,
            'Content-Length' => $postContent ? strlen($postContent) : null,
        ], $headers]);

        if (($useCurl && function_exists('curl_init')) || ini_get('safe_mode') || !ini_get('allow_url_fopen')) {
            $this->curl($bus);
        } else {
            $this->stream($bus);
        }

        foreach ($bus['resp_headers'] as $i => $line) {
            if ($i && strpos($line, ':')) {
                $arr = explode(':', $line, 2);
                $meta['headers'][strtolower($arr[0])] = trim($arr[1]);
            } else {
                if (preg_match('#^HTTP/([0-9.]+) ([0-9]+) (.*)$#', $line, $m)) {
                    static::$_lastRemoteHttpInfo['headers']['http'] = [
                        'unparsed' => $line,
                        'full' => $m[0],
                        'protocol' => $m[1],
                        'code' => $m[2],
                        'status' => $m[3],
                    ];
                } else {
                    static::$_lastRemoteHttpInfo['headers']['http'] = [
                        'unparsed' => $line,
                    ];
                }
            }
        }
        return $response;
    }

    protected function processMultipart(array &$bus)
    {

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_string($v) && !empty($v[0]) && $v[0] === '@') {
                    $bus['multipart'] = true;
                    break;
                }
            }
        }
        if ($method === 'POST' || $method === 'PUT') {
            if (!$multipart) {
                $contentType = 'application/x-www-form-urlencoded';
                $postContent = is_array($data) ? http_build_query($data) : $data;
            } else {
                $boundary = '--------------------------' . microtime(true);
                $contentType = 'multipart/form-data; boundary=' . $boundary;
                $postContent = '';
                //TODO: implement recursive forms
                foreach ($data as $k => $v) {
                    if (is_string($v) && $v[0] === '@') {
                        $filename     = substr($v, 1);
                        $fileContents = file_get_contents($filename);
                        #$fileContents = base64_encode($fileContents);
                        $fileContentsType = mime_content_type($filename);
                        $postContent .= "--{$boundary}\r\n" .
                            "Content-Type: {$fileContentsType}\r\n" .
                            "MIME-Version: 1.0\r\n" .
                            "Content-Disposition: form-data; name=\"{$k}\"; filename=\"" . basename($filename) . "\"\r\n" .
                            #"Content-Transfer-Encoding: base64\r\n" .
                            "\r\n" .
                            "{$fileContents}\r\n";
                    } else {
                        $postContent .= "--{$boundary}\r\n" .
                            "Content-Type: text/plain; charset=\"utf-8\"\r\n" .
                            "MIME-Version: 1.0\r\n" .
                            "Content-Disposition: form-data; name=\"{$k}\"\r\n" .
                            "\r\n" .
                            "{$v}\r\n";
                    }
                }
                $postContent .= "--{$boundary}--\r\n";
            }
        }
    }

    protected function curl(array &$bus)
    {
        $curlOpt = [
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_URL => $url,
            CURLOPT_ENCODING => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HEADER => true,
        ];
        if (!($curlCaInfo = ini_get('curl.cainfo')) || !file_exists($curlCaInfo)) {
            $curlOpt += [
                CURLOPT_CAINFO => $this->normalizePath(dirname(__DIR__) . '/ssl/cacert.pem'),
            ];
        }
        #if (!ini_get('safe_mode') && !ini_get('open_basedir')) {
        $curlOpt += [
            CURLOPT_FOLLOWLOCATION => true,
        ];
        #}
        if (false) { // TODO: figure out cookies handling
            $cookieDir = $this->BApp->storageRandomDir() . '/cache';
            $this->ensureDir($cookieDir);
            $cookie = tempnam($cookieDir, 'CURLCOOKIE');
            $curlOpt += [
                CURLOPT_COOKIEJAR => $cookie,
            ];
        }

        if ($method === 'POST') {
            $curlOpt += [
                CURLOPT_POSTFIELDS => $postContent,
                //CURLOPT_POST => 1,
                CURLOPT_CUSTOMREQUEST => 'POST',
            ];
            if (empty($options['use_customrequest_only'])) {
                //$curlOpt[CURLOPT_POST] = 1;
            }
        } elseif ($method === 'PUT') {
            $curlOpt += [
                CURLOPT_POSTFIELDS => $postContent,
                //CURLOPT_PUT => 1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
            ];
            if (empty($options['use_customrequest_only'])) {
                //$curlOpt[CURLOPT_PUT] = 1;
            }
        } elseif ($method === 'DELETE') {
            $curlOpt += [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
            ];
        }

        $curlOpt += [
            CURLOPT_HTTPHEADER => array_values($headers),
        ];

        if (!empty($options['proxy'])) {
            $curlOpt += [
                CURLOPT_PROXY => $options['proxy'],
                CURLOPT_PROXYTYPE => !empty($options['proxytype']) ? $options['proxytype'] : CURLPROXY_HTTP,
            ];
        }

        if (!empty($options['auth'])) {
            $curlOpt += [
                CURLOPT_USERPWD => $options['auth'],
            ];
        }
        /*
        $this->BDebug->log(print_r([
            'ts' => $this->BDb->now(),
            'data' => $data,
            'curlopts' => $curlOpt,
            'consts' => ['POSTFIELDS' => CURLOPT_POSTFIELDS, 'POST' => CURLOPT_POST],
        ], 1), 'remotehttp.log');
        */
        $ch = curl_init();
        curl_setopt_array($ch, $curlOpt);
        $rawResponse = curl_exec($ch);

//$curlConstants = get_defined_constants(true)['curl'];
//$curlOptInfo = [];
//foreach ($curlConstants as $name => $key) {
//    if (!empty($curlOpt[$key])) {
//        if (preg_match('#^CURLOPT#', $name)) {
//            $curlOptInfo[$name] = $curlOpt[$key];
//        }
//    }
//}
//echo "<xmp>"; print_r($curlOptInfo); echo $rawResponse; echo "</xmp>";

        list($headers, $response) = explode("\r\n\r\n", $rawResponse, 2) + ['', ''];
        static::$_lastRemoteHttpInfo = curl_getinfo($ch);
#echo '<xmp>'; var_dump(__METHOD__, $rawResponse, static::$_lastRemoteHttpInfo, $curlOpt); echo '</xmp>';
        $respHeaders = explode("\r\n", $headers);
        if (curl_errno($ch) != 0) {
            static::$_lastRemoteHttpInfo['errno'] = curl_errno($ch);
            static::$_lastRemoteHttpInfo['error'] = curl_error($ch);
        }
        curl_close($ch);
    }

    protected function stream(array &$bus)
    {

        $streamOptions = ['http' => [
            'protocol_version' => '1.0',
            'method' => $method,
            'timeout' => $timeout,
            'header' => [
                'User-Agent: ' . $userAgent,
                'Connection: close',
            ],
        ]];
        if ($headers) {
            $streamOptions['http']['header'] += array_values($headers);
        }
        if (!empty($options['proxy'])) {
            $streamOptions['http']['proxy'] = $options['proxy'];
        }
        if ($method === 'POST' || $method === 'PUT') {
            $streamOptions['http']['content'] = $postContent;
            $streamOptions['http']['header'][] = "Content-Type: {$contentType}"
                . "\r\nContent-Length: " . strlen($postContent) . "\r\n";

        }
        if (!empty($options['auth'])) {
            $streamOptions['http']['header'][] = sprintf("Authorization: Basic %s", base64_encode($options['auth']));
        }

        if (preg_match('#^(ssl|ftps|https):#', $url)) {
            $streamOptions['ssl'] = [
                'verify_peer' => true,
                'cafile' => dirname(__DIR__) . '/ssl/cacert.pem',
                'verify_depth' => 5,
            ];
        }
        if (empty($options['debug'])) {
            $oldErrorReporting = error_reporting(0);
        }
        $response = file_get_contents($url, false, stream_context_create($streamOptions));
#var_dump($response, $url, $streamOptions, $http_response_header); #exit(__METHOD__);
        if (empty($options['debug'])) {
            error_reporting($oldErrorReporting);
        }
        static::$_lastRemoteHttpInfo = []; //TODO: emulate curl data?
        $respHeaders = isset($http_response_header) ? $http_response_header : [];
        return [$response, $respHeaders];
    }
}