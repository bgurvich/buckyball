<?php

namespace Buckyball\Core\Storage;

use Buckyball\Core\Proto\Cls;

class Cache extends Cls
{

    protected $_backends = [];
    protected $_backendStatus = [];
    protected $_defaultBackend;

    public function __construct()
    {
        foreach (['File', 'Shmop', 'Apc', 'Memcache', 'Db'] as $type) {
            $this->addBackend($type, 'BCache_Backend_' . $type);
        }
        $this->_defaultBackend = $this->BConfig->get('core/cache/default_backend', 'file');
    }

    public function addBackend($type, $backend)
    {
        $type = strtolower($type);
        if (is_string($backend)) {
            if (!class_exists($backend)) {
                throw new BException('Invalid cache backend class name: ' . $backend . ' (' . $type . ')');
            }
            $backend = $this->{$backend};
        }
        if (!is_object($backend)) {
            throw new BException('Invalid backend for type: ' . $type);
        }
        if (!$backend instanceof BCache_Backend_Interface) {
            throw new BException('Invalid cache backend class interface: ' . $type);
        }
        $this->_backends[$type] = $backend;
        return $this;
    }

    public function setBackend($type)
    {
        $this->_defaultBackend = strtolower($type);
        return $this;
    }

    public function getAllBackends()
    {
        return $this->_backends;
    }

    public function getAllBackendsAsOptions()
    {
        $options = [];
        foreach ($this->getAllBackends() as $type => $backend) {
            $options[$type] = $type;
        }
        return $options;
    }

    public function getFastestAvailableBackend()
    {
        $minRank = 1000;
        $fastest = null;
        foreach ($this->_backends as $t => $backend) { // find fastest backend from available
            $info = $backend->info();
            if (empty($info['available'])) {
                continue;
            }
            if ($info['rank'] < $minRank) {
                $minRank = $info['rank'];
                $fastest = $t;
            }
        }
        return $fastest;
    }

    public function getBackend($type = null)
    {
        if (null === $type) { // type not specified
            $type = $this->_defaultBackend;
        } else {
            $type = strtolower($type);
        }
        $backend = $this->_backends[$type];
        if (empty($this->_backendStatus[$type])) {
            $info = $backend->info();
            if (empty($info['available'])) {
                throw new BException('Cache backend is not available: ' . $type);
            }
            $config = (array)$this->BConfig->get('core/cache/' . $type);
            $backend->init($config);
            $this->_backendStatus[$type] = true;
            $this->BDebug->debug('Default cache backend initialized: ' . $type);
        }
        return $this->_backends[$type];
    }

    public function load($key)
    {
        return $this->getBackend()->load($key);
    }

    public function loadMany($pattern)
    {
        return $this->getBackend()->loadMany($pattern);
    }

    public function save($key, $data, $ttl = null)
    {
        return $this->getBackend()->save($key, $data, $ttl);
    }

    public function delete($key)
    {
        return $this->getBackend()->delete($key);
    }

    public function deleteMany($pattern)
    {
        return $this->getBackend()->deleteMany($pattern);
    }

    public function gc()
    {
        return $this->getBackend()->gc();
    }

    public function deleteAll()
    {
        $backend = $this->getBackend();
        if (method_exists($backend, 'deleteAll')) {
            return $backend->deleteAll();
        }
        return false;
    }
}