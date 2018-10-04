<?php

namespace Buckyball\Core\Storage\Cache;

use Buckyball\Core\Iface\Cache\Backend;
use Buckyball\Core\Proto\Cls;

class File extends Cls implements Backend
{
    protected $_config = [];

    public function info()
    {
        return ['available' => true, 'rank' => 70];
    }

    public function init($config = [])
    {
        if (empty($config['dir'])) {
            $config['dir'] = $this->BConfig->get('fs/cache_dir');
        }
        if (!is_writable($config['dir'])) {
            $config['dir'] = sys_get_temp_dir() . '/fulleron/' . md5(__DIR__) . '/cache';
        }
        if (empty($config['default_ttl'])) {
            $config['default_ttl'] = 3600;
        }
        if (empty($config['file_type'])) {
            $config['file_type'] = 'json'; // dat, php
        }
        $this->_config = $config;
        return true;
    }

    protected function _filename($key)
    {
        $md5 = md5($key);
        return $this->_config['dir'] . '/' . substr($md5, 0, 2) . '/'
            . $this->BUtil->simplifyString($key) . '.' . substr($md5, 0, 10) . '.' . $this->_config['file_type'];
    }

    public function load($key)
    {
        $filename = $this->_filename($key);
        if (!file_exists($filename)) {
            return null;
        }
        $fileType = $this->_config['file_type'];
        switch ($fileType) {
            case 'dat':
            case 'json':
                $fp = fopen($filename, 'r');
                $metaRaw = fgets($fp, 1024);
                $meta = $fileType === 'dat' ? @unserialize($metaRaw) : json_decode($metaRaw, true);
                if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()) {
                    fclose($fp);
                    @unlink($filename);
                    return null;
                }
                for ($contents = ''; $chunk = fread($fp, 4096); $contents .= $chunk) ;
                fclose($fp);
                $data = $fileType === 'dat' ? @unserialize($contents) : json_decode($contents, true);
                break;

            case 'php':
                $array = include($filename);
                $meta = !empty($array['meta']) ? $array['meta'] : null;
                if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()) {
                    @unlink($filename);
                    return null;
                }
                $data = $array['data'];
                break;
        }
        return $data;

    }

    public function save($key, $data, $ttl = null)
    {
        $filename = $this->_filename($key);
        $dir = dirname($filename);
        $this->BUtil->ensureDir($dir);
        $meta = [
            'ts' => time(),
            'ttl' => !is_null($ttl) ? $ttl : $this->_config['default_ttl'],
            'key' => $key,
        ];
        switch ($this->_config['file_type']) {
            case 'dat':
                $contents = serialize($meta) . "\n" . serialize($data);
                break;

            case 'json':
                $contents = json_encode($meta) . "\n" . json_encode($data);
                break;

            case 'php':
                $contents = '<' . '?php return ' . var_export(['meta' => $meta, 'data' => $data], 1) . ';';
                break;
        }
        file_put_contents($filename, $contents);
        return true;
    }

    public function delete($key)
    {
        $filename = $this->_filename($key);
        if (!file_exists($filename)) {
            return false;
        }
        @unlink($filename);
        return true;
    }

    /**
     * Load many items found by pattern
     *
     * @todo implement regexp pattern
     *
     * @param mixed $pattern
     * @return array
     */
    public function loadMany($pattern)
    {
        $files = glob($this->_config['dir'] . '/*/*' . $this->BUtil->simplifyString($pattern) . '*');
        if (!$files) {
            return [];
        }
        $result = [];
        $fileType = $this->_config['file_type'];
        switch ($fileType) {
            case 'dat':
            case 'json':
                foreach ($files as $filename) {
                    $fp = fopen($filename, 'r');
                    $metaRaw = fgets($fp, 1024);
                    $meta = $fileType === 'dat' ? @unserialize($metaRaw) : json_decode($metaRaw, true);
                    if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()) {
                        fclose($fp);
                        @unlink($filename);
                        continue;
                    }
                    if (strpos($meta['key'], $pattern) !== false) { // TODO: regexp search without iterating all files
                        for ($contents = ''; $chunk = fread($fp, 4096); $contents .= $chunk);
                        $result[$meta['key']] = $fileType === 'dat' ? @unserialize($contents) : json_decode($contents, true);
                    }
                    fclose($fp);
                }
                break;

            case 'php':
                foreach ($files as $filename) {
                    $array = include($filename);
                    $meta = !empty($array['meta']) ? $array['meta'] : null;
                    if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()) {
                        @unlink($filename);
                        continue;
                    }
                    $result[$meta['key']] = $array['data'];
                }
                break;
        }
        return $result;
    }

    public function deleteMany($pattern)
    {
        if ($pattern === true || $pattern === false) { // true: remove ALL cache, false: remove EXPIRED cache
            $files = glob($this->_config['dir'] . '/*/*');
        } else {
            $files = glob($this->_config['dir'] . '/*/*' . $this->BUtil->simplifyString($pattern) . '*');
        }
        if (!$files) {
            return false;
        }
        $result = [];
        $fileType = $this->_config['file_type'];
        switch ($fileType) {
            case 'dat':
            case 'json':
                foreach ($files as $filename) {
                    if ($pattern === true) {
                        @unlink($filename);
                        continue;
                    }
                    $fp = fopen($filename, 'r');
                    $metaRaw = fgets($fp, 1024);
                    $meta = $fileType === 'dat' ? @unserialize($metaRaw) : json_decode($metaRaw, true);
                    fclose($fp);
                    if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()
                        || $pattern === false || strpos($meta['key'], $pattern) !== false // TODO: regexp search without iterating all files
                    ) {
                        @unlink($filename);
                    }
                }
                break;

            case 'php':
                foreach ($files as $filename) {
                    if ($pattern === true) {
                        @unlink($filename);
                        continue;
                    }
                    $array = include($filename);
                    $meta = !empty($array['meta']) ? $array['meta'] : null;
                    if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()
                        || $pattern === false || strpos($meta['key'], $pattern) !== false // TODO: regexp search without iterating all files
                    ) {
                        @unlink($filename);
                    }
                }
                break;
        }
        return true;
    }

    public function gc()
    {
        $this->deleteMany(false);
        return true;
    }

    public function deleteAll()
    {
        $this->BUtil->rmdirRecursive_YesIHaveCheckedThreeTimes($this->_config['dir']);
        return true;
    }
}