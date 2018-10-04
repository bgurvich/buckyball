<?php

namespace Buckyball\Core;

use Buckyball\Core\App\Module;
use Buckyball\Core\Data\Struct;
use Buckyball\Core\Iface\App\Area;
use Buckyball\Core\Proto\Cls;

class App extends Cls implements \Buckyball\Core\Iface\App
{
    /**
     * @var Struct
     */
    protected $env;

    /**
     * @var Struct
     */
    protected $config;

    /**
     * @var array
     */
    protected $classOverrides = [];

    /**
     * @var array
     */
    protected $singletons = [];

    /**
     * @var Area
     */
    protected $area;

    /**
     * App constructor.
     * @param $env
     */
    public function __construct($env)
    {
        $initEnv = [
            'SERVER' => $_SERVER,
            'GET' => $_GET,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            'COOKIE' => $_COOKIE,
        ];

        $rootDir = dirname(dirname(dirname(__DIR__)));
        $initEnv['fs'] = [
            'root_dir' => $rootDir,
            'pub_dir' => "{$rootDir}/pub",
            'var_dir' => "{$rootDir}/var",
            'config_dir' => "{$rootDir}/var/config",
            'cache_dir' => "{$rootDir}/var/cache",
            'code_dirs' => ["{$rootDir}/core", "{$rootDir}/local"],
        ];

        $server = $initEnv['SERVER'];
        $s = $server['HTTPS'] ? 's' : '';
        $host = $server['HTTP_HOST'] ?? null;
        $scriptName = $server['SCRIPT_NAME'] ?? null;
        $basePath = dirname($scriptName);

        $initEnv['areas'] = [
            'frontend' => [
                'url_prefix' => "http{$s}://{$host}{$scriptName}/",
                'root_path' => $basePath,
            ],
            'backend' => [
                'url_prefix' => "http{$s}://{$host}{$scriptName}/admin/",
                'root_path' => "{$basePath}/admin/",
            ],
        ];
        $this->env = $this->getInstance(Struct::class, [$initEnv]);
        $this->env->merge($env);
    }

    /**
     * @param array|null $env
     * @return App
     */
    static public function factory(?array $env = null): self
    {
        static $singleton;

        if (!empty($singleton) && null === $env) {
            return $singleton;
        }

        $app = new static($env);

        if (empty($singleton)) {
            $singleton = $app;
        }

        return $app;
    }

    /**
     * @param string $class
     * @param array|null $args
     * @return object
     */
    public function getInstance(string $class, ?array $args = []): object
    {
        if (!empty($this->classOverrides[$class])) {
            $class = $this->classOverrides[$class];
        }
        $refl = new ReflectionClass($class);
        $inst = $refl->newInstanceArgs($args);
        if ($inst instanceof Proto\Cls && !$inst instanceof self) {
            $inst->setApp($this);
        }
        return $inst;
    }

    /**
     * @param string $class
     * @return object
     */
    public function getSingleton(string $class): object
    {
        if (empty($this->singletons[$class])) {
            $this->singletons[$class] = $this->getInstance($class);
        }
        return $this->singletons[$class];
    }

    /**
     * @return App
     */
    public function run(string $areaClass): Area
    {
        ini_set('display_errors', 1);
        display_errors(E_ALL);

        $this->bootstrap();
        $area = $this->getSingleton($areaClass);
        $area->processRequest();
        return $area;
    }

    public function bootstrap(?string $areaClass = null): self
    {
        $this->loadLibs();

        foreach (['core', 'db', 'local'] as $f) {
            $this->loadConfig("{$this->env->get('config_dir')}/{$f}.php");
        }

        foreach (['core', 'local'] as $d) {
            spl_autoload_register(function ($class) use ($d) {
                $file = $this->env->get('root_dir') . '/' . $d . '/' . strtr($class, ['\\' => '/']) . '.php';
                if (file_exists($file)) {
                    include $file;
                    return true;
                }
                return false;
            });
        }

        $this->bootstrapModules();
    }

    public function bootstrapModules(): self
    {
        /** @var Module $singleton */
        $singleton = $this->getSingleton(Module::class);
        foreach (['core', 'local'] as $d) {
            $dir = "{$this->env['root_dir']}/{$d}";
            $modules = $singleton->findAllInDir($dir);
            /** @var Module $module */
            foreach ($modules as $module) {
                $module->loadManifest();
                // sorting by dep
            }
        }
    }

    public function loadConfigFromDir(string $dir): self
    {

    }

    public function saveConfigToFile(string $file, array $data): self
    {

    }

    public function getConfigByPath(string $path)
    {

    }

    public function getEnvByPath(string $path)
    {

    }

    public function loadLibs(): self
    {
        require_once 'lib/Toml.php';
        return $this;
    }

    public function __destruct()
    {
        parent::__destruct();
        unset($this->area, $this->env, $this->classOverrides, $this->singletons);
    }
}