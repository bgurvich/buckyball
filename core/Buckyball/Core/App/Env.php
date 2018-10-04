<?php

namespace Buckyball\Core\App;

use Buckyball\Core\Data\Struct;

class Env extends Struct
{
    public function __construct(array $env)
    {
        $this->set($this->getInitEnv());
        $this->set($env);
    }

    public function getInitEnv()
    {
        if (!empty($_SERVER['ORIG_SCRIPT_NAME'])) {
            $_SERVER['ORIG_SCRIPT_NAME'] = str_replace('/index.php/index.php', '/index.php', $_SERVER['ORIG_SCRIPT_NAME']);
        }
        if (!empty($_SERVER['ORIG_SCRIPT_FILENAME'])) {
            $_SERVER['ORIG_SCRIPT_FILENAME'] = str_replace('/index.php/index.php', '/index.php', $_SERVER['ORIG_SCRIPT_FILENAME']);
        }

        $env = [
            'SERVER' => $_SERVER,
            'GET' => $_GET,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            'COOKIE' => $_COOKIE,
        ];

        $rootDir = dirname(dirname(dirname(__DIR__)));
        $env['fs'] = [
            'root_dir' => $rootDir,
            'pub_dir' => "{$rootDir}/pub",
            'var_dir' => "{$rootDir}/var",
            'config_dir' => "{$rootDir}/var/config",
            'cache_dir' => "{$rootDir}/var/cache",
            'code_dirs' => ["{$rootDir}/core", "{$rootDir}/local"],
        ];

        $server = $env['SERVER'];
        $s = $server['HTTPS'] ? 's' : '';
        $host = $server['HTTP_HOST'] ?? null;
        $scriptName = $server['SCRIPT_NAME'] ?? null;
        $basePath = dirname($scriptName);

        $env['areas'] = [
            'frontend' => [
                'url_prefix' => "http{$s}://{$host}{$scriptName}/",
                'root_path' => $basePath,
            ],
            'backend' => [
                'url_prefix' => "http{$s}://{$host}{$scriptName}/admin/",
                'root_path' => "{$basePath}/admin/",
            ],
        ];
        return $env;
    }

    /**
     * Get current request URL
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        $host = $this->scheme() . '://' . $this->httpHost(true);
        if ($this->app()->config()->hideScriptName() && $this->app()->area() !== 'FCom_Admin') {
            $root = $this->webRoot();
        } else {
            $root = $this->scriptName();
        }
        $root = trim($root, '/');
        $path = ltrim($this->rawPath(), '/');
        $get = $this->rawGet();
        $url = $host . '/' . ($root ? $root . '/' : '') . $path . ($get ? '?' . $get : '');
        return $url;
    }

    /**
     * Client remote IP
     *
     * @return string
     */
    public function ip()
    {
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Server local IP
     *
     * @return string
     */
    public function serverIp()
    {
        return !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
    }

    /**
     * Server host name
     *
     * @return string
     */
    public function serverName()
    {
        return !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    /**
     * Host name from request headers
     *
     * @return string
     */
    public function httpHost($includePort = true)
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            return null;
        }
        if ($includePort) {
            return $_SERVER['HTTP_HOST'];
        }
        $a = explode(':', $_SERVER['HTTP_HOST']);
        return $a[0];
    }

    public function validateHttpHost($whitelist = null)
    {
        if (null === $whitelist) {
            $whitelist = $this->BConfig->get('web/http_host_whitelist');
        }
        if (!$whitelist) {
            return true;
        }
        $httpHost = $this->httpHost(false);

        foreach (explode(',', $whitelist) as $allowedHost) {
            if (preg_match('/(^|\.)' . preg_quote(trim($allowedHost, ' .')) .'$/i', $httpHost)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Port from request headers
     *
     * @return string
     */
    public function httpPort()
    {
        return !empty($_SERVER['HTTP_PORT']) ? $_SERVER['HTTP_PORT'] : null;
    }

    /**
     * Origin host name from request headers
     *
     * @return string
     */
    public function httpOrigin()
    {
        return !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
    }

    /**
     * Whether request is SSL
     *
     * @return bool
     */
    public function https()
    {
        return !empty($_SERVER['HTTPS']);
    }

    /**
     * Server protocol (HTTP/1.0 or HTTP/1.1)
     *
     * @return string
     */
    public function serverProtocol()
    {
        $protocol = "HTTP/1.0";
        if (isset($_SERVER['SERVER_PROTOCOL']) && stripos($_SERVER['SERVER_PROTOCOL'], "HTTP") >= 0) {
            $protocol = $_SERVER['SERVER_PROTOCOL'];
        }
        return $protocol;
    }

    public function scheme()
    {
        return $this->https() ? 'https' : 'http';
    }

    static protected $_headers;

    public function headers()
    {
        if (!static::$_headers) {
            foreach ($_SERVER as $k => $v) {
                if (strpos($k, 'HTTP_') === 0) {
                    $name = str_replace([' '], ['-'], strtolower(preg_replace(['/^HTTP_/', '/_/'], ['', ' '], $k)));
                    static::$_headers[$name] = $v;
                }
            }
        }
        return static::$_headers;
    }

    /**
     * Get all mime types accepted by client browser, or return the preferred type
     *
     * @param array|string|null $supportedTypes
     * @return array|null
     */
    public function acceptTypes($supportedTypes = null)
    {
        if (empty($_SERVER['HTTP_ACCEPT'])) {
            return [];
        }
        static $acceptTypes = null;
        if (null === $acceptTypes) {
            $accept = [];
            $acceptTypes = [];
            foreach (preg_split('#\s*,\s*#', $_SERVER['HTTP_ACCEPT']) as $i => $part) {
                if (preg_match("#^(\S+)\s*;\s*(?:q|level)=([0-9\.]+)#i", $part, $m)) {
                    $accept[] = ['pos' => $i, 'type' => $m[1], 'q' => (double)$m[2]];
                } else {
                    $accept[] = ['pos' => $i, 'type' => $part, 'q' => 1];
                }
            }
            usort($accept, function ($a, $b) {
                return ($a['q'] === $b['q']) ? ($a['pos'] - $b['pos']) : ($b['q'] - $a['q']);
            });
            foreach ($accept as $a) {
                $acceptTypes[$a['type']] = $a['type'];
            }
        }
        if (null === $supportedTypes) {
            return $acceptTypes;
        }
        $supportedTypes = (array)$supportedTypes;
        foreach ($acceptTypes as $type) {
            if (in_array($type, $supportedTypes)) {
                return $type;
            }
        }
        if (!empty($acceptTypes['*/*'])) {
            return $supportedTypes[0];
        }
        return false;
    }

    /**
     * Retrieve language based on HTTP_ACCEPT_LANGUAGE
     * @return string
     */
    public function acceptLanguage()
    {
        $langs = [];

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            // break up string into pieces (languages and q factors)
            $langRegex = '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i';
            preg_match_all($langRegex , $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

            if (count($lang_parse[1])) {
                // create a list like "en" => 0.8
                $langs = array_combine($lang_parse[1], $lang_parse[4]);

                // set default to 1 for any without q factor
                foreach ($langs as $lang => $val) {
                    if ($val === '') $langs[$lang] = 1;
                }

                // sort list based on value
                arsort($langs, SORT_NUMERIC);
            }
        }

        //if no language detected return false
        if (empty($langs)) {
            return false;
        }

        foreach ($langs as $toplang => $_) {
            break;
        }
        //return en, de, es, it.... first two characters of language code
        return substr($toplang, 0, 2);
    }

    public function language($fallbackToBrowser = false)
    {
        if (null === static::$_language) {
            $this->rawPath();
            if (null === static::$_language && $fallbackToBrowser) {
                static::$_language = $this->acceptLanguage();
            }
        }
        return static::$_language;
    }

    public function userAgent($pattern = null)
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (null === $pattern) {
            return $userAgent;
        }
        preg_match($pattern, $userAgent, $match);
        return $match;
    }

    /**
     * Web server document root dir
     *
     * @return string
     */
    public function docRoot()
    {
        return !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : null;
    }

    /**
     * Entry point script web path
     *
     * @return string
     */
    public function scriptName()
    {
        return !empty($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) :
            (!empty($_SERVER['ORIG_SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['ORIG_SCRIPT_NAME']) : null);
    }

    /**
     * Entry point script file name
     *
     * @return string
     */
    public function scriptFilename()
    {
        return !empty($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']) :
            (!empty($_SERVER['ORIG_SCRIPT_FILENAME']) ? str_replace('\\', '/', $_SERVER['ORIG_SCRIPT_FILENAME']) : null);
    }

    /**
     * Entry point directory name
     *
     * @return string
     */
    public function scriptDir()
    {
        return ($script = $this->scriptFilename()) ? dirname($script) : null;
    }

    protected static $_webRootCache = [];

    /**
     * Web root path for current application
     *
     * If request is /folder1/folder2/index.php, return /folder1/folder2/
     *
     * @param $parent if required a parent of current web root, specify depth
     * @return string
     */
    public function webRoot($parentDepth = 0)
    {
        if (isset(static::$_webRootCache[$parentDepth])) {
            return static::$_webRootCache[$parentDepth];
        }
        $scriptName = $this->scriptName();
        if (empty($scriptName)) {
            return null;
        }
        if (substr($scriptName, -1) !== '/') {
            $scriptName = dirname($scriptName);
        }
        $root = rtrim(str_replace(['//', '\\'], ['/', '/'], $scriptName), '/');

        if ($parentDepth) {
            $arr = explode('/', rtrim($root, '/'));
            $len = sizeof($arr) - $parentDepth;
            $root = $len > 1 ? join('/', array_slice($arr, 0, $len)) : '/';
        }
        if (!$root) {
            $root = '/';
        }
        static::$_webRootCache[$parentDepth] = $root;

        return $root;
    }

    /**
     * Full base URL, including scheme and domain name
     *
     * @todo optional omit http(s):
     * @param null|boolean $forceSecure - if not null, force scheme
     * @param boolean $includeQuery - add origin query string
     * @return string
     */
    public function baseUrl($forceSecure = null, $includeQuery = false)
    {
        if (null === $forceSecure) {
            $scheme = $this->https() ? 'https:' : '';
        } else {
            $scheme = $forceSecure ? 'https:' : '';
        }
        $url = $scheme . '//' . $this->serverName() . $this->webRoot();
        if ($includeQuery && ($query = $this->rawGet())) {
            $url .= '?' . $query;
        }
        return $url;
    }

    /**
     * Full request path, one part or slice of path
     *
     * @param int $offset
     * @param int $length
     * @return string
     */
    public function path($offset, $length = null)
    {
        $pathInfo = $this->rawPath();
        if (empty($pathInfo)) {
            return null;
        }

        $path = explode('/', ltrim($pathInfo, '/'));
        if (null === $length) {
            return isset($path[$offset]) ? $path[$offset] : null;
        }
        return join('/', array_slice($path, $offset, true === $length ? null : $length));
    }

    /**
     * Raw path string
     *
     * @return string
     */
    public function rawPath()
    {
        static $path;

        if (null === $path) {
            $path = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] :
                (!empty($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : null);
            /*
                (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] :
                    (!empty($_SERVER['SERVER_URL']) ? $_SERVER['SERVER_URL'] : '/')
                )
            );*/

            if (null === $path && !empty($_SERVER['REQUEST_URI'])) {
                $path = preg_replace('#[?].*$#', '', $_SERVER['REQUEST_URI']);
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);

                if ($scriptDir && $scriptDir !== '/') {
                    $rootPathRe = '#' . preg_quote(dirname($_SERVER['SCRIPT_NAME']), '#') . '#';
                    $path = preg_replace($rootPathRe, '', $path);
                }
            }
            // nginx rewrite fix
            $basename = basename($this->scriptName());
            if ($basename && ($basename !== '/' || $basename[0] !== '/')) {
                $path = preg_replace('#^/.*?' . preg_quote($basename, '#') . '#', '', $path);
            }
            $re = '#^/(([a-z]{2})(_[A-Z]{2})?)(/.*|$)#';
            if ($this->BConfig->get('web/language_in_url') && preg_match($re, $path, $match)) {
                static::$_language = $match[2];
                $this->BLocale->setCurrentLocale($match[1]);
                $path = $match[4];
            }

            if (!$path) {
                $path = '/';
            }
        }

        return $path;
    }

    /**
     * PATH_TRANSLATED
     *
     */
    public function pathTranslated()
    {
        return !empty($_SERVER['PATH_TRANSLATED']) ? $_SERVER['PATH_TRANSLATED'] :
            (!empty($_SERVER['ORIG_PATH_TRANSLATED']) ? $_SERVER['ORIG_PATH_TRANSLATED'] : '/');
    }

    public function getCookieDomain()
    {
        $confDomain = $this->BConfig->get('cookie/domain');
        $httpHost = $this->httpHost(false);
        if (!empty($confDomain)) {
            $allowedDomains = explode('|', $confDomain);
            if (in_array($httpHost, $allowedDomains)) {
                $domain = $httpHost;
            } else {
                $domain = $allowedDomains[0];
            }
        } else {
            $domain = $httpHost;
        }
        return $domain;
    }

    public function getCookiePath()
    {
        $confPath = $this->BConfig->get('cookie/path');
        $path = $confPath ? $confPath : $this->BConfig->get('web/base_store');
        if (empty($path)) {
            $path = $this->webRoot();
        }
        $path = rtrim($path, '/') . '/';
        return $path;
    }

    public function getCookieConfigJson()
    {
        $config = $this->BConfig->get('cookie');
        return $this->BUtil->toJson([
            'domain' => $this->getCookieDomain(),
            'path' => $this->getCookiePath(),
            'expires' => (!empty($config['timeout']) ? $config['timeout'] / 86400 : null),
            'secure' => $this->https(),
        ]);
    }

    /**
     * Set or retrieve cookie value
     *
     * @param string $name Cookie name
     * @param string $value Cookie value to be set
     * @param int $lifespan Optional lifespan, default from config
     * @param string $path Optional cookie path, default from config
     * @param string $domain Optional cookie domain, default from config
     * @return bool
     */
    public function cookie($name, $value = null, $lifespan = null, $path = null, $domain = null, $secure = null, $httpOnly = null)
    {
        if (null === $value) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
        if (false === $value) {
            unset($_COOKIE[$name]);
            return $this->cookie($name, '-CLEAR-', -100000);
        }

        $config = $this->BConfig->get('cookie');
        $lifespan = null !== $lifespan ? $lifespan : (!empty($config['timeout']) ? $config['timeout'] : null);
        $path = null !== $path ? $path : (!empty($config['path']) ? $config['path'] : $this->getCookiePath());
        $domain = null !== $domain ? $domain : (!empty($config['domain']) ? $config['domain'] : $this->getCookieDomain());
        $secure = null !== $secure ? $secure : $this->https();
        $httpOnly = null !== $httpOnly ? $httpOnly : true;
        return setcookie($name, $value, time() + $lifespan, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Verify that HTTP_HOST or HTTP_ORIGIN
     *
     * @param string $method (HOST|ORIGIN|OR|AND)
     * @param string $host
     * @return boolean
     */
    public function verifyOriginHostIp($method = 'OR', $host = null)
    {
        $ip = $this->ip();
        if (!$host) {
            $host = $this->httpHost(false);
        }
        $origin = $this->httpOrigin();
        $hostIPs = gethostbynamel($host);
        $hostMatches = $host && $method != 'ORIGIN' ? in_array($ip, (array)$hostIPs) : false;
        $originIPs = gethostbynamel($origin);
        $originMatches = $origin && $method != 'HOST' ? in_array($ip, (array)$originIPs) : false;
        switch ($method) {
            case 'HOST': return $hostMatches;
            case 'ORIGIN': return $originMatches;
            case 'AND': return $hostMatches && $originMatches;
            case 'OR': return $hostMatches || $originMatches;
        }
        return false;
    }

    /**
     * Validate that URL is within boundaries of domain and webroot
     */
    public function isUrlLocal($url, $checkPath = false)
    {
        if (!$url) {
            return null;
        }
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return true;
        }
        if ($parsed['host'] !== $this->httpHost(false)) {
            return false;
        }
        if ($checkPath) {
            $webRoot = $this->BConfig->get('web/root_dir');
            if (!preg_match('#^' . preg_quote($webRoot, '#') . '#', $parsed['path'])) {
                return false;
            }
        }
        return true;
    }

    public function modRewriteEnabled()
    {
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $modRewrite = in_array('mod_rewrite', $modules);
        } else {
            $modRewrite =  strtolower(getenv('SELLVANA_MOD_REWRITE')) == 'on' ? true : false;
        }
        return $modRewrite;
    }
}