<?php

namespace Buckyball\Core\Iface\Cache;

interface Backend
{
    public function info();

    public function init($config = []);

    public function load($key);

    public function save($key, $data, $ttl = null);

    public function delete($key);

    public function loadMany($pattern);

    public function deleteMany($pattern);

    public function gc();
}