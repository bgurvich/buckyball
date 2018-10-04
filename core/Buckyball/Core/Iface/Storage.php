<?php

namespace Buckyball\Core\Iface;

interface Storage
{
    public function init(array $config) : self;

    public function findOneByKey(string $key, ?string $value) : ?array;

    //public function saveModel(Model $model): self;
}