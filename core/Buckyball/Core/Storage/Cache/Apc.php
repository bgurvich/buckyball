<?php

namespace Buckyball\Core\Storage\Cache;

use Buckyball\Core\Iface\Cache\Backend;

class Apc extends Cls implements Backend
{
    protected $_config;

    public function info()
    {
        return ['available' => function_exists('apc_fetch'), 'rank' => 10];
    }

    public function init($config = [])
    {
        if (empty($config['prefix'])) {
            $config['prefix'] = substr(md5(__DIR__), 0, 16) . 'Apc.php/';
        }
        if (empty($config['default_ttl'])) {
            $config['default_ttl'] = 3600;
        }
        $this->_config = $config;
        return true;
    }

    public function load($key)
    {
        $fullKey = $this->_config['prefix'] . $key;
        return apc_fetch($fullKey);
    }

    public function save($key, $data, $ttl = null)
    {
        $ttl = !is_null($ttl) ? $ttl : $this->_config['default_ttl'];
        $cacheKey = $this->_config['prefix'] . $key;
        /** @see http://stackoverflow.com/questions/10494744/deadlock-with-apc-exists-apc-add-apc-php */
        #if (apc_exists($cacheKey)) {
        #    apc_delete($cacheKey);
        #}
        return apc_store($cacheKey, $data, (int)$ttl);
    }

    public function delete($key)
    {
        return apc_delete($this->_config['prefix'] . $key);
    }

    public function loadMany($pattern)
    {
        //TODO: regexp: new APCIterator('user', '/^MY_APC_TESTA/', APC_ITER_VALUE);
        $items = new APCIterator('user');
        $prefix = $this->_config['prefix'];
        $result = [];
        foreach ($items as $item) {
            $key = $item['key'];
            if (strpos($key, $prefix) !== 0) {
                continue;
            }
            if ($pattern === true || strpos($key, $pattern) !== false) {
                $result[$key] = apc_fetch($key);
            }
        }
        return $result;
    }

    public function deleteMany($pattern)
    {
        if ($pattern === false) {
            return false; // not implemented for APC, has internal expiration
        }
        $items = new APCIterator('user');
        $prefix = $this->_config['prefix'];
        foreach ($items as $item) {
            $key = $item['key'];
            if (strpos($key, $prefix) !== 0) {
                continue;
            }
            if ($pattern === true || strpos($key, $pattern) !== false) {
                apc_delete($key);
            }
        }
        return true;
    }

    public function gc()
    {
        return true;
    }

    public function deleteAll()
    {
        $items = new APCIterator('user');
        $prefix = $this->_config['prefix'];
        foreach ($items as $item) {
            apc_delete($item['key']);
        }
        return true;
    }
}