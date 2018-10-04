<?php

namespace Buckyball\Core\Proto;

use Buckyball\Core\Iface\App;

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

    public function __destruct()
    {
        unset($app);
    }
}