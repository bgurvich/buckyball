<?php

namespace Buckyball\Core\Iface\Storage;

use Buckyball\Core\Iface\Model;

interface Db
{
    public function init(array $config) : self;

    public function findOneByKey(string $key, ?string $value) : ?array;

    public function saveModel(Model $model): self;
}