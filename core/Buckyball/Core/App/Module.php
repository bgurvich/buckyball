<?php

namespace Buckyball\Core\App;

use Buckyball\Core\Proto\Cls;

class Module extends Cls implements \Buckyball\Core\Iface\App\Module
{

    /**
     * @var array
     */
    protected $env;

    /**
     * @var array
     */
    protected $manifest;

    /**
     * @var array
     */
    protected $config;

    public function findAllInDir(string $dir): array
    {
        $manifestFiles = glob($dir . '/*/*/manifest.*');
        $modules = [];
        foreach ($manifestFiles as $file) {
            $env = [
                'module_dir' => dirname($file),
                'manifest_file' => $file,
            ];
            $module = $this->factory($env)->loadManifest();
            $modules[] = $module;
        }
        return $modules;
    }

    public function factory(array $env): self
    {
        return new static($env);
    }

    public function __construct(array $env)
    {
        $this->env = $env;
    }

    public function loadManifest(): self
    {
        if (empty($this->env['manifest_file'])) {
            throw new \Buckyball\Core\Exception\App\Module('Manifest file name is empty.');
        }
        $file = $this->env['manifest_file'];
        if (empty(!file_exists($file))) {
            throw new \Buckyball\Core\Exception\App\Module('Manifest file not found.');
        }
        $ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'TOML':
                $manifest = Toml::parseFile($file);
                break;

            default:
                throw new \Buckyball\Core\Exception\App\Module('Manifest file unknown format.');
        }
        $manifest['module']['name'] = preg_replace('#[^#', '', $this->env['module_dir']);
        $this->manifest = $manifest;
        return $this;
    }
}