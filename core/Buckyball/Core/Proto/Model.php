<?php

namespace Buckyball\Core\Proto;

use \Buckyball\Core\Iface\Storage\Db;
use \Buckyball\Core\Iface\Model as IfaceModel;
use \Buckyball\Core\Storage\Db\Pdo as Pdo;

abstract class Model extends Cls implements IfaceModel
{
    const DB_CLASS = Pdo::class;
    const DB_TABLE = 'model';

    const ID_FIELD = 'id';
    const UUID_FIELD = 'uuid';
    const TREE_FIELD = 'tree_serialized';
    const CREATED_FIELD = 'created_at';
    const UPDATED_FIELD = 'updated_at';

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $origData = [];

    /**
     * @var Db
     */
    protected $db;

    public function setDb(Db $db): self
    {
        $this->db = $db;
        return $this;
    }

    public function getDb(): Db
    {
        return $this->db;
    }

    public function hydrate(array $data): self
    {
        $this->origData = $data;
        $this->data = [];
        foreach ($data as $key => $val) {
            $this->set($key, $val);
        }
        return $this;
    }

    public function getRawData(): array
    {
        return $this->data;
    }

    public function set(string $field, ?string $value): self
    {
        $this->data[$field] = $value;
        return $this;
    }

    public function get(string $field)
    {
        return $this->data[$field] ?? null;
    }

    public function load(int $id): Model
    {
        return $this->loadBy($id, static::ID_FIELD);
    }

    public function loadBy(string $value, string $field): self
    {
        return $this->factory()->hydrate($this->getDb()->findOneByKey($field, $value));
    }

    public function save(): self
    {
        $this->getDb()->saveModel($this);
        return $this;
    }

    public function __destruct()
    {
        parent::__destruct();
        unset($this->db, $this->data, $this->origData);
    }
}