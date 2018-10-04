<?php

namespace Buckyball\Core\App;

use Buckyball\Core\Proto\Cls;

class Session extends Cls
{
    public function addHandler($name, $class)
    {
        $this->_availableHandlers[$name] = $class;
    }

    public function getHandlers()
    {
        $handlers = array_keys($this->_availableHandlers);
        return $handlers ? array_combine($handlers, $handlers) : [];
    }

    public function open()
    {

        if (headers_sent()) {
            BDebug::warning("Headers already sent, can't start session");
            return $this;
        }

        $this->_isOpen = true;
        $this->_setSessionPhpFlags();
        $this->_setSessionName();
        $this->_processSessionHandler();
        $this->_setSessionId($id);
        $this->_sessionStart();
        $this->_phpSessionOpen = true;
        $this->_validateSession();
        $this->_sessionId = session_id();
        $this->_initSessionData();
    }

    protected function _setSessionPhpFlags()
    {
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);

        ini_set('session.gc_maxlifetime', $this->_getRememberMeTtl());
        ini_set('session.gc_divisor', 100);
        ini_set('session.gc_probability', 1);

        $useStrictMode = isset($this->_config['use_strict_mode']) ? $this->_config['use_strict_mode'] : 1;
        ini_set('session.use_strict_mode', $useStrictMode);

        ini_set('session.cookie_httponly', 1);

        if ($this->BRequest->https()) {
            ini_set('session.cookie_secure', 1);
        }
    }

    protected function _setSessionName()
    {
        session_name(!empty($this->_config['name']) ? $this->_config['name'] : $this->_defaultSessionCookieName);
    }

    protected function _processSessionHandler()
    {
        if (!empty($this->_config['session_handler'])
            && $this->_config['session_handler'] !== 'default'
            && !empty($this->_availableHandlers[$this->_config['session_handler']])
        ) {
            $class = $this->_availableHandlers[$this->_config['session_handler']];
            $this->{$class}->register($this->_getCookieTtl());
        } else {
            //session_set_cookie_params($ttl, $path, $domain);
            $dir = $this->BConfig->get('fs/session_dir');
            if ($dir) {
                $this->BUtil->ensureDir($dir);
                if (is_dir($dir) && is_writable($dir)) {
                    session_save_path($dir);
                }
            }
            #var_dump($dir);
        }
    }

    protected function _setSessionId($id = null)
    {
        if (!$id) {
            if (!empty($_COOKIE[session_name()])) {
                $id = $_COOKIE[session_name()];
                $this->_idFromRequest = true;
            }
        }
        if ($id && preg_match('#^[A-Za-z0-9]{26,60}$#', $id)) {
            session_id($id);
        }
        return $id;
    }

    protected function _sessionStart($ttl = null)
    {
        if (null === $ttl) {
            $ttl = $this->_getCookieTtl();
        }

        $path = $this->BRequest->getCookiePath();
        $domain = $this->BRequest->getCookieDomain();
        $https = $this->BRequest->https();

        header_remove('Set-Cookie');

        session_set_cookie_params($ttl, $path, $domain, $https, true);

        session_start();

        // update session cookie expiration to reflect current visit
        // @see http://www.php.net/manual/en/function.session-set-cookie-params.php#100657
        setcookie(session_name(), session_id(), time() + $ttl, $path, $domain, $https, true);
    }

    protected function _validateSession()
    {
        $ip = $this->BRequest->ip();
        $agent = $this->BRequest->userAgent();

        $refresh = false;
        if ($this->_idFromRequest && !isset($_SESSION['_ip'])) {
            $refresh = true;
        }
        if (!$refresh && !empty($this->_config['session_check_ip'])) {
            if ((!empty($_SESSION['_ip']) && $_SESSION['_ip'] !== $ip)) {
                $refresh = true;
            }
        }
        if (!$refresh && !empty($this->_config['session_check_agent'])) {
            if (!empty($_SESSION['_agent']) && $_SESSION['_agent'] !== $agent) {
                $refresh = true;
            }
        }
        if (!$refresh && !empty($_SESSION['_expires']) && $_SESSION['_expires'] < time()) {
            $refresh = true;
        }
        if ($refresh) {
            $_SESSION = [];
            session_destroy();
            $this->_sessionStart();
        }
    }

    protected function _initSessionData()
    {
        $ip = $this->BRequest->ip();
        $agent = $this->BRequest->userAgent();

        if (empty($_SESSION['_ip'])) {
            $_SESSION['_ip'] = $ip;
        }
        if (empty($_SESSION['_agent'])) {
            $_SESSION['_agent'] = $agent;
        }
        $namespace = !empty($this->_config['session_namespace']) ? $this->_config['session_namespace'] : 'default';
        if (empty($_SESSION[$namespace])) {
            $_SESSION[$namespace] = [];
        }
        $nsData =& $_SESSION[$namespace];
        $nsData['_'] = time();

        if (empty($nsData['current_language'])) {
            $lang = $this->BRequest->language(true);
            if (!empty($lang)) {
                $nsData['current_language'] = $lang;
            }
        }

        #$nsData['_locale'] = $this->BConfig->get('locale');
        /*
        if (!empty($nsData['_locale'])) {
            if (is_array($nsData['_locale'])) {
                foreach ($nsData['_locale'] as $c => $l) {
                    setlocale($c, $l);
                }
            } elseif (is_string($nsData['_locale'])) {
                setlocale(LC_ALL, $nsData['_locale']);
            }
        } else {
            setLocale(LC_ALL, 'en_US.UTF-8');
        }
        */
        setLocale(LC_ALL, 'en_US.UTF-8');

        if (!empty($nsData['_timezone'])) {
            date_default_timezone_set($nsData['_timezone']);
        }
    }

    /**
     * Regenerate session ID
     *
     * @see http://php.net/manual/en/function.session-regenerate-id.php#87905
     * @return $this
     */
    public function regenerateId()
    {
        $this->open();

        $oldSessionId = session_id();

        //@session_regenerate_id((bool)$this->BConfig->get('cookie/delete_old_session'));
        @session_regenerate_id(false);

        $newSessionId = session_id();

        // close old and new session to allow other scripts to use them
        session_write_close();

        // start old session to save new session information (for long polling sleeper requests)
        session_id($oldSessionId);
        $this->_sessionStart();
        $_SESSION['_new_session_id'] = $newSessionId;
        $_SESSION['_expires'] = time() + 70; // expire old session in 70 seconds (give time for long polling return)
        session_write_close();

        // final start of new session
        session_id($newSessionId);
        $this->_sessionStart();

        $this->_idFromRequest = false;

        $this->BEvents->fire(__METHOD__ . ':after', ['old_session_id' => $oldSessionId, 'session_id' => $newSessionId]);

        //$this->BSession->set('_regenerate_id', 1);
        //session_id($this->BUtil->randomString(26, '0123456789abcdefghijklmnopqrstuvwxyz'));

        return $this;
    }

    /**
     * Used for long polling sleeper requests, when returning from browser
     *
     * @param array|null $dataToMerge
     * @return $this
     */
    public function switchToNewSessionIfExists(array $dataToMerge = null)
    {
        if (!empty($_SESSION['_new_session_id'])) {
            session_write_close();

            session_id($_SESSION['_new_session_id']);
            $this->_sessionStart();

            if ($dataToMerge) {
                $hlp = $this->BUtil;
                foreach ($dataToMerge as $key => $data) {
                    $_SESSION[$key] = !empty($_SESSION[$key]) ? $hlp->arrayMerge($_SESSION[$key], $data) : $data;
                }
            }
        }
        return $this;
    }

    /**
     * Get session ID
     *
     * @return string
     */
    public function sessionId()
    {
        $this->open();

        return $this->_sessionId;
    }


    public function get($key = null, $default = null)
    {
        $this->open();

        $namespace = !empty($this->_config['session_namespace']) ? $this->_config['session_namespace'] : 'default';
        if (empty($_SESSION[$namespace])) {
            return null;
        }

        $data = $_SESSION[$namespace];

        if ($key === null) {
            return $data;
        }

        if (strpos($key, '/') !== false) {
            $pathArr = explode('/', trim($key, '/'));
            foreach ($pathArr as $k) {
                if (!isset($data[$k])) {
                    return $default;
                }
                $data = $data[$k];
            }
            return $data;
        }

        return isset($data[$key]) ? $data[$key] : $default;
    }

    public function set($key, $value = null, $merge = false)
    {
        $this->open();
        $namespace = !empty($this->_config['session_namespace']) ? $this->_config['session_namespace'] : 'default';

        if (true === $key) {
            $_SESSION[$namespace] = $value;
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
            return $this;
        }

        $node =& $_SESSION[$namespace];
        if (strpos($key, '/') !== false) {
            foreach (explode('/', trim($key, '/')) as $k) {
                $node =& $node[$k];
            }
        } else {
            $node =& $node[$key];
        }

        if ($node !== $value) {
            $this->setDirty();
        }

        if ($merge) {
            $node = $this->BUtil->arrayMerge((array)$node, (array)$value);
        } else {
            $node = $value;
        }
        return $this;
    }

    public function pop($key)
    {
        $data = $this->get($key);
        $this->set($key, null);
        return $data;
    }

    /**
     * Write session variable changes and close PHP session
     *
     * @return BSession
     */
    public function close()
    {
        session_write_close();
        return $this;
    }

    public function destroy()
    {
        $path = $this->BRequest->getCookiePath();
        $domain = $this->BRequest->getCookieDomain();
        $https = $this->BRequest->https();
        if (!isset($_SESSION) && !headers_sent()) {
            session_set_cookie_params(0, $path, $domain, $https, true);
            session_start();
        }
        session_destroy();

        setcookie(session_name(), '', time() - 3600, $path, $domain, $https, true);
#echo "<pre>"; var_dump($_SESSION, $_COOKIE, session_name(), $path, $domain); exit;
        return $this;
    }

    public function csrfToken($validating = false, $hashReferrer = null)
    {
        $csrfToken = $this->get('_csrf_token');
        if (!$csrfToken) {
            $csrfToken = $this->BUtil->randomString(32);
            $this->set('_csrf_token', $csrfToken);
        }
        if (null === $hashReferrer) {
            $hashReferrer = $this->BConfig->get('web/csrf_check_method') === 'token+referrer';
        }

        if ($hashReferrer) {
            if ($validating) {
                $url = $this->BRequest->referrer();
            } else {
                $url = $this->BRequest->currentUrl();
            }
            $url = rtrim(str_replace('/index.php', '', $url), '/?&#');
            return sha1($csrfToken . $url);
        }
        return $csrfToken;
    }

    public function validateCsrfToken($token)
    {
        return $token === $this->csrfToken(true);
    }
}