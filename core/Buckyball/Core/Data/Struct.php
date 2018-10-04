<?php

namespace Buckyball\Core\Data;

use Buckyball\Core\Proto\Cls;

class Struct extends Cls
{
    protected $data = [];

    protected $glue = '/';

    public function __construct(array $data)
    {
        $this->setData($data);
    }

    public function setGlue(string $glue): self
    {
        $this->glue = $glue;
        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function set(string $path, $data): self
    {
        $keys = explode($this->glue, $path);
        $ref = &$this->data;
        foreach ($keys as $key) {
            if (isset($ref) && !is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $data;
        return $this;
    }

    public function get(string $path)
    {
        $keys = explode($this->glue, $path);
        $ref = &$this->data;
        foreach ($keys as $key) {
            if (is_array($ref) && array_key_exists($key, $ref)) {
                $ref = &$ref[$key];
            } else {
                return null;
            }
        }
        return $ref;
    }

    public function unset(string $path)
    {
        $keys = explode($this->glue, $path);
        $ref = &$this->data;
        while ($keys) {
            $key = array_shift($keys);
            if (empty($ref) || !is_array($ref)) {
                return $this;
            }
            if (empty($keys)) {
                unset($ref[$key]);
                return $this;
            }
            $ref = &$ref[$key];
        }
        return $this;
    }

    public function merge(string $path, array $data): self
    {

    }

    public function __destruct()
    {
        parent::__destruct();
        unset($this->data);
    }
}