<?php

namespace Buckyball\Core;

use Buckyball\Core\App\Config;
use Buckyball\Core\App\Env;
use Buckyball\Core\App\Module;
use Buckyball\Core\App\Request;
use Buckyball\Core\App\Response;
use Buckyball\Core\App\Session;
use Buckyball\Core\Data\Struct;
use Buckyball\Core\Iface\App\Area;
use Buckyball\Core\Proto\Cls;
use Buckyball\Core\Util\Arr;
use Buckyball\Core\Util\Email;
use Buckyball\Core\Util\Http;
use Buckyball\Core\Util\Str;

spl_autoload_register(function ($class) {
    $file = dirname(dirname(__DIR__)) . '/' . strtr($class, ['\\' => '/']) . '.php';
    if (file_exists($file)) {
        include $file;
        return true;
    }
    return false;
});

class App extends Cls implements Iface\App
{
    /**
     * @var Env
     */
    protected $env;

    /**
     * @var Config
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
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Email
     */
    protected $email;

    /**
     * @var Http
     */
    protected $http;

    /**
     * @var Str
     */
    protected $str;

    /**
     * @var Arr
     */
    protected $arr;

    /**
     * App constructor.
     * @param $env
     */
    public function __construct($env)
    {
        foreach (['core', 'local'] as $d) {
            $this->autoload($this->env->get('root_dir') . '/' . $d);
        }

        $this->env = $this->getInstance(Env::class, [$env]);
        $this->request = $this->getInstance(Request::class)->fromEnv($this->env);
        $this->session = $this->getInstance(Session::class);

        $this->email = $this->getSingleton(Email::class);
        $this->http = $this->getSingleton(Http::class);
        $this->str = $this->getSingleton(Str::class);
        $this->arr = $this->getSingleton(Arr::class);
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
        $this->bootstrap();
        $area = $this->getSingleton($areaClass);
        $area->processRequest();
        return $area;
    }

    public function bootstrap(?string $areaClass = null): self
    {
        $this->session()->start();

        $this->loadLibs();

        foreach (['core', 'db', 'local'] as $f) {
            $this->loadConfig("{$this->env->get('config_dir')}/{$f}.php");
        }

        $this->bootstrapModules();
    }

    public function autoload(string $dir): self
    {
        spl_autoload_register(function ($class) use ($dir) {
            $file = $dir . '/' . strtr($class, ['\\' => '/']) . '.php';
            if (file_exists($file)) {
                include $file;
                return true;
            }
            return false;
        });
        return $this;
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

    public function env(): Struct
    {
        return $this->env;
    }

    public function config(): Struct
    {
        return $this->config;
    }

    public function __destruct()
    {
        parent::__destruct();
        unset($this->area, $this->env, $this->classOverrides, $this->singletons);
    }
}