<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

defined('WEB_URI') or define('WEB_URI', '');
defined('APP_ROOT') or define('APP_ROOT', dirname(dirname(__FILE__)));
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('SETTINGS_FILE') or define('SETTINGS_FILE', APP_ROOT . '/settings.php');
defined('DEFAULT_TIMEZONE') or define('DEFAULT_TIMEZONE', 'Asia/Shanghai');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
@date_default_timezone_set(DEFAULT_TIMEZONE);


/*获取当前应用*/
function app($url=false)
{
    static $app = null; //函数内缓存
    if ($url === false) {
        if (! is_null($app)) { //获取已初始化应用
            return $app;
        }
        $url = $_SERVER['REQUEST_URI'];
    }
    $app = new Application(str_replace(WEB_URI, '', $url));
    if ($error_log = $app->getSetting('error_log')) {
        @error_log($error_log); //错误日志
    }
    spl_autoload_register(array($app, 'autoload'));
    return $app;
}


/*跳转到网站其他位置*/
function redirect($url, $code=301)
{
    if ($code == 301) { //永久跳转
        if (true) { //TODO:网站内部跳转
            return run(app($url));
        }
        @header('HTTP/1.1 301 Moved Permanently');
    }
    @header('Location: ' . $url);
}


/*路由*/
function route($uri, $callback, $module=null, array $methods=null)
{
    app()->router->add($uri, $callback, $module, $methods);
}


/*运行*/
function run(Application $app)
{
    $response = $app->handleRouter();
    if (is_null($response)) {
        echo $app->abort(500);
        return;
    }
    echo $response;
}


/**
 * 过程描述，相当于匿名函数
 */
class Procedure
{
    public $subject = null;
    public $method = '';
    public $args = array();

    public function __construct($subject, $method, array $args=array())
    {
        $this->subject = $subject;
        $this->method = $method;
        $this->args = $args;
    }

    /*执行过程得到结果*/
    public function invoke()
    {
        if (is_null($this->subject)) {
            $func = $this->method;
        }
        else if ($this->method === '__construct') {
            $ref = new ReflectionClass($this->subject);
            $func = array($ref, 'newInstanceArgs');
        }
        else {
            if ($this->subject instanceof Procedure) {
                $this->subject->invoke();
            }
            $func = array($this->subject, $this->method);
        }
        $args = array_merge($this->args, func_get_args());
        return call_user_func_array($func, $args);
    }
}


/**
 * 全局注册表
 * 用于自动加载类文件，根据配置生成类对象
 */
class Application
{
    public static $autoload_classes = array( //加载类列表
        'HamRouter' =>  'router.php',
        'Database' =>  'database.php',
        'Collection' => 'database.php',
        'SQLiteral' => 'database.php',
        'KLogger' =>  'logger.php',
        'KFileLogger' =>  'logger.php',
        'KHTTPLogger' =>  'logger.php',
        'KPDOLogger' =>  'logger.php',
        'HTTPClient' =>  'httpclient.php',
    );
    protected $search_pathes = array(); //类搜索路径
    protected $settings = array(); //配置
    protected $services = array(); //服务
    protected $envname = '';
    public $current_url = false;
    public $request = null;
    public $response = null;

    public function __construct($current_url)
    {
        $url_pics = parse_url($current_url);
        if (is_array($url_pics)) {
            $this->current_url = $url_pics['path'];
        }
    }

    public function setEnvname($envname='')
    {
        $this->envname = $envname;
        return $this;
    }

    /*自动加载*/
    public function autoload($class)
    {
        if (array_key_exists($class, self::$autoload_classes)) {
            require_once APP_ROOT . '/library/' . self::$autoload_classes[$class];
            return true;
        }
        foreach ($this->search_pathes as $search_path) {
            $php_file = rtrim($search_path, '/') . '/' . strtolower($class) . '.php';
            if (file_exists($php_file)) {
                require_once $php_file;
                if (class_exists($class, false)) {
                    return true;
                }
            }
        }
    }

    public function parseSettings()
    {
        if (empty($this->settings)) { //加载配置
            $settings = (include SETTINGS_FILE);
            $this->settings = ($settings === false) ? array() : $settings;
            if (isset($this->settings['search_pathes'])) {
                $this->search_pathes += $this->settings['search_pathes'];
            }
        }
    }

    public function getSetting($name, $subname='')
    {
        $this->parseSettings();
        if (array_key_exists($name, $this->settings)) {
            $setting = $this->settings[$name];
            if (! empty($subname) && is_array($setting) && array_key_exists($subname, $setting)) {
                if ($subname !== 'default' && is_array($setting['default'])) {
                    $setting = array_merge($setting['default'], $setting[$subname]);
                }
                else {
                    $setting = $setting[$subname];
                }
            }
            return $setting;
        }
    }

    public function putService($obj, $name=null)
    {
        if (is_null($name)) {
            $name = get_class($obj);
        }
        $this->services[$name] = $obj;
    }

    public function getService($name)
    {
        @list($name, $subname) = explode('.', $name, 2);
        $subname = empty($subname) ? $this->envname : $subname;
        $setting = $this->getSetting($name, $subname);
        if (is_callable($setting)) {
            return $setting();
        }
        else if (is_array($setting)) {
            $call = new Procedure(array_shift($setting), '__construct');
            $args = array();
            foreach ($setting as $key => $value) {
                if (substr($key, 0, 1) === '#') {
                    $key = substr($key, 1) . '.' . $value;
                    array_push($args, $this->$key);
                }
                else {
                    array_push($args, $value);
                }
            }
            return $call->invoke($args);
        }
        else if (is_string($setting) && class_exists($setting, true)) {
            return new $setting();
        }
    }

    public function __get($name)
    {
        if (! isset($this->services[$name])) {
            $this->putService($this->getService($name), $name);
        }
        return $this->services[$name];
    }
    
    /*找到URL对应函数或对象获得输出*/
    public function handleRouter()
    {
        if ($this->current_url === false) {
            return;
        }
        if (empty($this->response)) {
            @list($handler, $args) = $this->router->find($this->current_url);
            if (! is_null($handler)) {
                $this->response = $handler->emit($_SERVER['REQUEST_METHOD'], $args);
            }
        }
        return $this->response;
    }
    
    public function abort($code, $message='')
    {
        if(php_sapi_name() != 'cli') {
            @header("Status: {$code}", false, $code);
        }
        return "<h1>{$code}</h1><p>{$message}</p>";
    }
}



/**
 * URL路由器
 * 简化自James Cleveland的Ham 
 */
class HamRouter
{
    public static $route_types = array(
        '<int>' => '([0-9\-]+)',
        '<float>' => '([0-9\.\-]+)',
        '<string>' => '([a-zA-Z0-9\-_]+)',
        '<path>' => '([a-zA-Z0-9\-_\/])'
    );
    protected $routes = array();
    protected $prefix = '';
    
    public function add($uri, $callback, $module=null, array $methods=null)
    {
        $module = is_null($module) ? strtok($uri, '/') : $module;
        $uri = str_replace('/', '\/', preg_quote(rtrim($uri, '/')));
        if (empty($methods)) {
            $methods = array('GET', 'POST', 'HEAD');
        }
        $this->compile($uri, $callback, $module, $methods);
    }
    
    public function compile($uri, $callback, $module, array $methods=array())
    {
        $keys = array_map('preg_quote', array_keys(self::$route_types));
        $values = array_values(self::$route_types);
        $route = str_replace($keys, $values, $uri);
        $wildcard = '';
        if ($callback instanceof self) {
            $callback->prefix = $uri;
            $wildcard = '(.*)?';
        }        
        else if ($callback instanceof Handler) {
            $callback->module = $module;
        }
        else {
            $callback = Handler::create($callback, $methods);
            $callback->module = $module;
        }
        $route_key = '/^' . $route . '\/?' . $wildcard . '$/';
        $this->routes[$route_key] = $callback;
        return $route_key;
    }
    
    public function find($url)
    {
        foreach ($this->routes as $route_key => $handler) {
            if (preg_match($route_key, $url, $args)) {
                if ($handler instanceof self) {
                    $inner_url = substr($url, strlen($handler->prefix));
                    return $handler->find($inner_url);
                }
                array_shift($args); //丢掉第一个元素，完整匹配的URL
                return array($handler, $args);
            }
        }
        return array(null, array());
    }
}


/**
 * URL路由器
 */
class Handler
{
    public $module = '';
    public $callbacks = array();
    
    public static function create($callback, array $methods=array())
    {
        $obj = new self();
        if (! empty($callback)) {
            foreach ($methods as $method) {
                $obj->callbacks[strtoupper($method)] = $callback;
            }
        }
        return $obj;
    }
    
    public function emit($method='GET', array $args=array())
    {
        if (method_exists($this, $method)) { //PHP方法名不区分大小写
            $callback = array($this, $method);
        }
        else if (isset($this->callbacks[$method])) {
            $callback = $this->callbacks[$method];
            //加载当前模块中所有的handlers
            if (is_string($callback) && ! function_exists($callback)) {
                require_once WEB_ROOT . '/views/' . $this->module . '.php';
            }
        }
        if (is_callable($callback)) {
            if (count($args) === 0) { //保留函数默认值
                return call_user_func($callback);
            }
            else {
                return call_user_func_array($callback, $args);
            }
        }
    }
}


/**
 * 模板引擎
 */
class Templater
{
    public $template_dir = '';
    public $cache_dir = '';
    public $globals = array();
    private $extend_files = array();
    private $template_blocks = array();
    private $current_block = '';

    public function __construct($template_dir, $cache_dir='', array $globals=array())
    {
        $this->template_dir = rtrim($template_dir, DS);
        $this->cache_dir = rtrim($cache_dir, DS);
        $this->globals = $globals;
    }

    public function partial($template_file, array $context=array())
    {
        extract($context);
        ob_start();
        include $this->template_dir . DS . $template_file; //入口模板
        if (! empty($this->extend_files)) {
            $layout_file = array_pop($this->extend_files);
            foreach ($this->extend_files as $file) { //中间继承模板
                include $this->template_dir . DS . $file;
            }
            extract($this->template_blocks);
            include $this->template_dir . DS . $layout_file; //布局模板
        }
        return ob_get_clean();
    }

    public function render($template_file, array $context=array())
    {
        $context = array_merge($this->globals, $context);
        return $this->partial($template_file, $context);
    }


    /* 注意: 必须自己传递context，如果想共享render中的context，请在模板中
       使用 include $this->template_dir . DS . $template_file;
       代替 $this->include_tpl($template_file); */
    public function include_tpl($template_file, array $context=array())
    {
        extract($this->globals);
        extract($context);
        include $this->template_dir . DS . $template_file;
    }

    public function extend_tpl($template_file)
    {
        array_push($this->extend_files, $template_file);
    }

    public function block_start($block_name='content')
    {
        $this->current_block = $block_name;
        ob_start();
    }

    public function block_end()
    {
        $this->template_blocks[ $this->current_block ] = ob_get_clean();
    }
}
