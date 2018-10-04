<?php

namespace Buckyball\Core\Iface;

interface Model
{
    public function set(string $field, ?string $value): self;
    public function get(string $field);

    public function hydrate(array $data): self;

    public function create(): self;
    public function load(int $id): self;

    public function save();
}