<?php

namespace Buckyball\Core\Controller;

use Buckyball\Core\Data\Struct;

class Request extends Struct
{
    public function fromEnv(Struct $env)
    {
        $this->set('GET', $env->get('GET'));
        $this->set('POST', $env->get('POST'));
        $this->set('REQUEST', $env->get('REQUEST'));
        $this->set('COOKIE', $env->get('COOKIE'));
    }

    /**
     * Whether request is AJAX
     *
     * @return bool
     */
    public function xhr()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    }

    /**
     * Request method:
     *
     * @return string GET|POST|HEAD|PUT|DELETE
     */
    public function method()
    {
        return !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    /**
     * Request query variables
     *
     * @param string $key
     * @return array|string|null
     */
    public function get($key = null, $default = null)
    {
        // Encountered this in some nginx + apache environments
        if (empty($_GET) && !empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }
        return null === $key ? $_GET : (isset($_GET[$key]) ? $_GET[$key] : $default);
    }

    /**
     * Request query as string
     *
     * @return string
     */
    public function rawGet()
    {
        return !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    /**
     * Request POST variables
     *
     * @param string|null $key
     * @return array|string|null
     */
    public function post($key = null)
    {
        return null === $key ? $_POST : (isset($_POST[$key]) ? $_POST[$key] : null);
    }

    /**
     * Request raw POST text
     *
     * @param bool $json Receive request as JSON
     * @param bool $asObject Return as object vs array
     * @return object|array|string
     */
    public function rawPost()
    {
        $post = file_get_contents('php://input');
        return $post;
    }

    /**
     * Request array/object from JSON API call
     *
     * @param boolean $asObject
     * @return mixed
     */
    public function json($asObject = false)
    {
        return $this->BUtil->fromJson(static::rawPost(), $asObject);
    }

    /**
     * Request variable (GET|POST|COOKIE)
     *
     * @param string|null $key
     * @return array|string|null
     */
    public function request($key = null)
    {
        return null === $key ? $_REQUEST : (isset($_REQUEST[$key]) ? $_REQUEST[$key] : null);
    }

    /**
     * Get request referrer
     *
     * @see http://en.wikipedia.org/wiki/HTTP_referrer#Origin_of_the_term_referer
     * @param string $default default value to use in case there is no referrer available
     * @return string|null
     */
    public function referrer($default = null)
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $default;
    }

    public function receiveFiles($source, $targetDir, $typesRegex = null)
    {
        if (is_string($source)) {
            if (!empty($_FILES[$source])) {
                $source = $_FILES[$source];
            } else {
                //TODO: missing enctype="multipart/form-data" ?
                throw new BException('Missing enctype="multipart/form-data"?');
            }
        }
        if (empty($source)) {
            return;
        }
        $result = [];

        $uploadErrors = [
            UPLOAD_ERR_OK         => (('No errors.')),
            UPLOAD_ERR_INI_SIZE   => (('Larger than upload_max_filesize.')),
            UPLOAD_ERR_FORM_SIZE  => (('Larger than form MAX_FILE_SIZE.')),
            UPLOAD_ERR_PARTIAL    => (('Partial upload.')),
            UPLOAD_ERR_NO_FILE    => (('No file.')),
            UPLOAD_ERR_NO_TMP_DIR => (('No temporary directory.')),
            UPLOAD_ERR_CANT_WRITE => (("Can't write to disk.")),
            UPLOAD_ERR_EXTENSION  => (('File upload stopped by extension.'))
        ];
        if (is_array($source['error'])) {
            foreach ($source['error'] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) {
                    $tmpName = $source['tmp_name'][$key];
                    $name = $source['name'][$key];
                    $type = $source['type'][$key];
                    if (null !== $typesRegex && !preg_match('#' . $typesRegex . '#i', $type)) {
                        $result[$key] = ['error' => 'invalid_type', 'tp' => 1, 'type' => $type, 'name' => $name];
                        continue;
                    }
                    $this->BUtil->moveUploadedFileSafely($tmpName, $targetDir . '/' . $name, ['@media_dir', '@random_dir']);
                    $result[$key] = ['name' => $name, 'tp' => 2, 'type' => $type, 'target' => $targetDir . '/' . $name];
                } else {
                    $message = !empty($uploadErrors[$error]) ? $uploadErrors[$error] : null;
                    $result[$key] = ['error' => $error, 'message' => $message, 'tp' => 3];
                }
            }
        } else {
            $error = $source['error'];
            if ($error == UPLOAD_ERR_OK) {
                $tmpName = $source['tmp_name'];
                $name = $source['name'];
                $type = $source['type'];
                if (null !== $typesRegex && !preg_match('#' . $typesRegex . '#i', $type)) {
                    $result[] = ['error' => 'invalid_type', 'tp' => 4, 'type' => $type, 'pattern' => $typesRegex,
                        'source' => $source, 'name' => $name];
                } else {
                    $this->BUtil->moveUploadedFileSafely($tmpName, $targetDir . '/' . $name, ['@media_dir', '@random_dir']);
                    $result[] = ['name' => $name, 'type' => $type, 'target' => $targetDir . '/' . $name];
                }
            } else {
                $message = !empty($uploadErrors[$error]) ? $uploadErrors[$error] : null;
                $result[] = ['error' => $error, 'message' => $message, 'tp' => 5];
            }
        }
        return $result;
    }

    public function getAvailableCsrfMethods($includeEmpty = false)
    {
        $methods = [
            'token' => (('Token')),
            'origin' => (('Origin')),
            'referrer' => (('Referrer')),
            'token+referrer' => 'Token & Referrer'
        ];

        if ($includeEmpty) {
            $methods = ['' => ''] + $methods;
        }

        return $methods;
    }

    /**
     * Check whether the request can be CSRF attack
     *
     * Uses HTTP_REFERER header to compare with current host and path.
     * By default only POST, DELETE, PUT requests are protected
     * Only these methods should be used for data manipulation.
     *
     * The following specific cases will return csrf true:
     * - posting from different host or web root path
     * - posting from https to http
     *
     * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
     *
     * @param string $checkMethod
     * @param mixed $httpMethods
     * @throws BException
     * @return boolean
     */
    public function csrf($checkMethod = null, $httpMethods = null)
    {
        $c = $this->BConfig;
        if (null === $httpMethods) {
            $m = $c->get('web/csrf_http_methods');
        }
        if (!$httpMethods) {
            $httpMethods = ['POST', 'PUT', 'DELETE'];
        } elseif (is_string($httpMethods)) {
            $httpMethods = array_map('trim', explode(',', $httpMethods));
        } elseif (!is_array($httpMethods)) {
            throw new BException('Invalid HTTP Methods argument');
        }
        if (!in_array($this->method(), $httpMethods)) {
            return false; // not one of checked methods, pass
        }

        $whitelist = $c->get('web/csrf_path_whitelist');
        if ($whitelist) {
            $path = $this->rawPath();
            foreach ((array)$whitelist as $pattern) {
                if (preg_match($pattern, $path)) {
                    return false;
                }
            }
        }

        if (null === $checkMethod) {
            $m = $c->get('web/csrf_check_method');
            $checkMethod = $m ? $m : 'token';
        }

        switch ($checkMethod) {
            case 'referrer':
                $ref = $this->referrer();
                if (!$ref) {
                    return true; // no referrer sent, high prob. csrf
                }
                $p = parse_url($ref);
                $p['path'] = preg_replace('#/+#', '/', $p['path']); // ignore duplicate slashes
                $webRoot = $c->get('web/csrf_web_root');
                if (!$webRoot) {
                    $webRoot = $c->get('web/base_src');
                }
                if (!$webRoot) {
                    $webRoot = $this->webRoot();
                }
                if ($p['host'] !== $this->httpHost(false) || $webRoot && strpos($p['path'], $webRoot) !== 0) {
                    return true; // referrer host or doc root path do not match, high prob. csrf
                }
                return false; // not csrf

            case 'origin':
                $origin = $this->httpOrigin();
                if (!$origin) {
                    return true;
                }
                $p = parse_url($origin);
                if ($p['host'] !== $this->httpHost(false)) {
                    return true;
                }
                return false;
                break;

            case 'token':
            case 'token+referrer':
                if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                    $receivedToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
                } elseif (!empty($_POST['X-CSRF-TOKEN'])) {
                    $receivedToken = $_POST['X-CSRF-TOKEN'];
                }
                return empty($receivedToken) || !$this->BSession->validateCsrfToken($receivedToken);


            default:
                throw new BException('Invalid CSRF check method: ' . $checkMethod);
        }
    }

    public function addRequestFieldsWhitelist($whitelist)
    {
        foreach ((array)$whitelist as $urlPath => $fieldPaths) {
            foreach ($fieldPaths as $fieldPath => $allowTags) {
                if (is_numeric($fieldPath)) {
                    $fieldPath = $allowTags;
                    $allowTags = '*';
                }
                $this->_postTagsWhitelist[$urlPath][$fieldPath] = $allowTags;
            }
        }
        return $this;
    }

    public function stripRequestFieldsTags()
    {
        static $alreadyStripped;
        if ($alreadyStripped) {
            return null;
        }

        mb_internal_encoding('UTF-8');
        if (version_compare(PHP_VERSION, '5.6.0', '<')) {
            // below emits deprecated errors on php 5.6
            iconv_set_encoding('input_encoding', 'UTF-8');
            iconv_set_encoding('internal_encoding', 'UTF-8');
            iconv_set_encoding('output_encoding', 'UTF-8');
        }

        $data = ['GET' => & $_GET, 'POST' => & $_POST, 'REQUEST' => & $_REQUEST, 'COOKIE' => & $_COOKIE];
        $urlPath = rtrim($this->rawPath(), '/');
        $this->stripTagsRecursive($data, $urlPath);
        $alreadyStripped = true;
        return $this;
    }

    /**
     * @param $data
     * @param $forUrlPath
     * @param null $curPath
     */
    public function stripTagsRecursive(&$data, $forUrlPath, $curPath = null)
    {
#var_dump($data, $forUrlPath, $curPath);
        $allowedTags = $this->getAllowedTags();
        foreach ($data as $k => &$v) {
            $childPath = null === $curPath ? $k : ($curPath . '/' . $k);
#var_dump($childPath);
            if (is_array($v)) {
                $this->stripTagsRecursive($v,  $forUrlPath, $childPath);
            } elseif (!empty($v) && !is_numeric($v)) {
                if ($v === 'PLACEHOLDER~TO~REMOVE') {
                    unset($data[$k]);
                } elseif (!mb_check_encoding($v)) {
                    $v = null;
                } elseif (empty($this->_postTagsWhitelist[$forUrlPath][$childPath])) {
                    $v = strip_tags($v);
                } else {
                    $tags = $this->_postTagsWhitelist[$forUrlPath][$childPath];
                    if ('+' === $tags) {
                        $tags = $allowedTags;
                    }
                    if ('*' !== $tags) {
                        $v = strip_tags($v, $tags);
                        $v = preg_replace('/
                            < [a-z:-]+ \s .*? (
                                [a-z]+ \s* = \s* [\'"] \s* javascript \s* :   # src="javascript:..."
                                |
                                on[a-z]+ \s* = \s*   # onerror="..." onclick="..." onhover="..."
                            ) .*? >
                        /ix', '', $v);
                    }
                }
            }
        }
        unset($v);
    }

    public function getAllowedTags()
    {
        $tags = "<a><b><blockquote><code><del><dd><dl><dt><em><h1><i><img><kbd><li><ol><p><pre><s><sup>'
            . '<sub><strong><strike><ul><br><hr>";
        return $tags;
    }
}