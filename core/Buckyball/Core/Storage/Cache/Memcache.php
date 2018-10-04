<?php

namespace Buckyball\Core\Storage\Cache;

use Buckyball\Core\Iface\Cache\Backend;

class Memcache extends Cls implements Backend
{
    protected $_type;
    protected $_config;
    protected $_conn;
    protected $_flags;

    public function info()
    {
        #return ['available' => false];

        //TODO: explicit configuration
        if (class_exists('Memcache', false)) {
            $this->_type = 'Memcache';
        } elseif (class_exists('Memcached', false)) {
            $this->_type = 'Memcached';
        } else {
            return ['available' => false];
        }

        return ['available' => $this->_type && $this->init($this->_config), 'rank' => 10];
    }

    public function init($config = [])
    {
        if ($this->_conn) {
            return true;
        }
        if (empty($config['prefix'])) {
            $config['prefix'] = substr(md5(__DIR__), 0, 16) . 'Memcache.php/';
        }
        if (empty($config['host'])) {
            $config['host'] = 'localhost';
        }
        if (empty($config['port'])) {
            $config['port'] = 11211;
        }
        $this->_config = $config;
        #$this->_flags = !empty($config['compress']) ? MEMCACHE_COMPRESSED : 0;
        switch ($this->_type) {
            case 'Memcache':
                $this->_conn = new Memcache;
                return @$this->_conn->pconnect($config['host'], $config['port']);

            case 'Memcached':
                $this->_conn = new Memcached('sellvana');
                return $this->_conn->addServer($config['host'], $config['port']);
        }
    }

    public function load($key)
    {
        return $this->_conn->get($this->_config['prefix'] . $key);
    }

    public function save($key, $data, $ttl = null)
    {
        switch ($this->_type) {
            case 'Memcache':
                $flag = 0;#!empty($this->_config['compress']) ? MEMCACHE_COMPRESSED : 0;
                $ttl1 = is_null($ttl) ? 0 : time() + $ttl;
                return $this->_conn->set($this->_config['prefix'] . $key, $data, $flag, $ttl1);

            case 'Memcached':
                return $this->_conn->set($this->_config['prefix'] . $key, $data, $ttl);
        }
    }

    public function delete($key)
    {
        return $this->_conn->delete($this->_config['prefix'] . $key);
    }

    public function loadMany($pattern)
    {
        return false; // not implemented
    }

    public function deleteMany($pattern)
    {
        return false; // not implemented
    }

    public function gc()
    {
        return true; // not needed, ttl handled internally
    }

    public function deleteAll()
    {
        $this->_conn->flush();
        return true;
    }
}