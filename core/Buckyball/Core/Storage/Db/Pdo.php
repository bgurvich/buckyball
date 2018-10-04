<?php

namespace Buckyball\Core\Storage\Db;

use \Buckyball\Core\Iface\Model;
use \Buckyball\Core\Proto\Storage\Db as ProtoDb;
use \Buckyball\Core\Iface\Storage\Db as IfaceDb;

class Pdo extends ProtoDb implements IfaceDb
{
    public function factory(array $config = null): self
    {

    }

    public function findOneByKey(string $key, ?string $value): ?array
    {

    }

    public function saveModel(Model $model)
    {

    }
}