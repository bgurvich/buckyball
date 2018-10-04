<?php

namespace Buckyball\Core\Proto;

use Buckyball\Core\App;

abstract class Cls
{
    /**
     * @var App
     */
    protected $app;

    public function setApp(App $app): self
    {
        $this->app = $app;
        return $this;
    }

    public function app(): App
    {
        return $this->app;
    }

    public function inst(string $class, ?array $args = []): self
    {
        return $this->app->getInstance($class, $args);
    }

    public function __destruct()
    {
        unset($app);
    }
}