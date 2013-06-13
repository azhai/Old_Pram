<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

defined('DEBUG_MODE') or define('DEBUG_MODE', false);
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
    if (is_null($app)) { //获取已初始化应用
        $app = new Application();
        spl_autoload_register(array($app, 'autoload'));
    }
    return $app;
}


/*跳转到网站其他位置*/
function redirect($url, $code=301)
{
    if ($code == 301) { //永久跳转
        if (true) { //TODO:网站内部跳转
            return run(app(), $url);
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
function run(Application $app, $url=false)
{
    if ($url === false) {
        $url = $_SERVER['REQUEST_URI'];
    }
    $response = $app->handleRouter($url);
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

    public static function exec($func, array $args=null)
    {
        if (empty($args)) { //保留函数默认值
            return call_user_func($func);
        }
        else {
            return call_user_func_array($func, $args);
        }
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
            if ($this->subject instanceof self) {
                $this->subject->invoke();
            }
            $func = array($this->subject, $this->method);
        }
        $args = array_merge($this->args, func_get_args());
        return self::exec($func, $args);
    }
}


/**
 * 全局注册表
 * 用于自动加载类文件，根据配置生成类对象
 */
class Application
{
    public static $autoload_classes = array( //加载类列表
        'Listener' =>  'event.php',
        'Observer' =>  'event.php',
        'Database' =>  'database.php',
        'Collection' => 'database.php',
        'Model' => 'database.php',
        'SQLiteral' => 'database.php',
        'KLogger' =>  'logger.php',
        'KFileLogger' =>  'logger.php',
        'KHTTPLogger' =>  'logger.php',
        'KPDOLogger' =>  'logger.php',
        'DummyCacher' =>  'cacher.php',
        'FileCacher' =>  'cacher.php',
        'APCacher' =>  'cacher.php',
        'RedisCacher' =>  'cacher.php',
        'HTTPClient' =>  'httpclient.php',
    );
    protected $search_pathes = array(); //类搜索路径
    protected $settings = array(); //配置
    protected $services = array(); //服务
    protected $envname = '';
    public $request = null;
    public $response = null;

    public function __construct()
    {
        if ($error_log = $this->getSetting('error_log')) {
            @error_log($error_log); //错误日志
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
            $classes = array('Hanlder', 'Model', 'Collection', 'Listener');
            if (! in_array($class, $classes)) {
                $filename = str_replace($classes, array_fill(0, count($classes), ''), $class);
            }
            $php_file = rtrim($search_path, '/') . '/' . strtolower($filename) . '.php';
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
            return Procedure::exec($setting);
            //return $setting();
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
    public function handleRouter($url)
    {
        $current_url = '';
        $url_pics = parse_url(str_replace(WEB_URI, '', $url));
        if (is_array($url_pics)) {
            $current_url = $url_pics['path'];
        }
        if (empty($this->response)) {
            $route = $this->router->match($current_url);
            $handler = Handler::initialize($route, $_SERVER['REQUEST_METHOD']);
            if (! is_null($handler)) {
                $this->response = $handler->emit($this->router->route_args);
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
        '<page>' => '([0-9]*)\/?([0-9]*)\/?',
        '<path>' => '([a-zA-Z0-9\-_\/])',
    );
    public $current_url = '';
    public $url_format = '';
    public $route_key = false;
    public $route_args = array();
    protected $routes = array();
    protected $prefix = '';

    public function add($uri, $callback, $module=null, array $methods=null)
    {
        $module = is_null($module) ? strtok($uri, '/') : $module;
        $uri = str_replace('/', '\/', preg_quote(rtrim($uri, '/')));
        $keys = array_map('preg_quote', array_keys(self::$route_types));
        $values = array_values(self::$route_types);
        $route_url = str_replace($keys, $values, $uri);
        if (empty($methods)) {
            $methods = array('GET', 'POST', 'HEAD');
        }
        $this->compile($route_url, $callback, $module, $methods);
    }

    public function compile($route_url, $callback, $module, array $methods=array())
    {
        $wildcard = '';
        if ($callback instanceof self) {
            $route = $callback;
            $route->prefix = $route_url;
            $wildcard = '(.*)?';
        }
        else {
            $route = array(
                'module' => $module,
                'callback' => $callback,
                'methods' => $methods,
            );
        }
        $route_key = '/^' . $route_url . '\/?' . $wildcard . '$/';
        $this->routes[$route_key] = $route;
        return $route_key;
    }

    public function match($url)
    {
        foreach ($this->routes as $route_key => $route) {
            if (preg_match($route_key, $url, $args)) {
                if ($route instanceof self) {
                    $inner_url = substr($url, strlen($route->prefix));
                    return $route->match($inner_url);
                }
                array_shift($args); //丢掉第一个元素，完整匹配的URL
                $this->current_url = $url;
                $this->route_key = $route_key;
                $this->route_args = $args;
                return $route;
            }
        }
    }
    
    public function getUrlFormat()
    {
        if (empty($this->url_format)) {
            $url_format = preg_replace('/\([^\)]+\)/', '%s', $this->route_key);
            $url_format = str_replace(
                array('\/', '?', '^', '$'), 
                array('/', '', '', ''), 
                $url_format
            );
            $this->url_format = '/' . trim($url_format, '/') . '/';
        }
        return $this->url_format;
    }
    
    public function urlForCurrent(array $args=null, $reverse=false)
    {
        if (empty($args)) {
            return $this->current_url;
        }
        $url_format = $this->getUrlFormat();
        $offset = 0;
        $length = count($args);
        if ($reverse === true) {
            $offset = -$length;
            $args = ($length > 1) ? array_reverse($args) : $args;
        }
        $route_args = $this->route_args;
        array_splice($route_args, $offset, $length, $args);
        return vsprintf($url_format, $route_args);
    }
}


/**
 * URL路由器
 */
class Handler
{
    public $app = null;
    public $module = '';
    public $callback = '';
    
    public function __construct($module, $callback='')
    {
        $this->module = $module;
        $this->callback = $callback;
        $this->app = app();
    }
    
    public static function initialize($route, $method='GET')
    {
        if (empty($route)) {
            return app()->abort(404); //Page not found
        }
        $method = strtoupper($method);
        if (is_array($route['methods']) && ! in_array($method, $route['methods'])) {
            return app()->abort(405);  //Method not allowed
        }
        //加载View所在文件
        $filename = WEB_ROOT . '/views/' . $route['module'] . '.php';
        if (file_exists($filename)) {
            require_once $filename;
        }
        $handler_name = $route['callback'];
        if (is_subclass_of($handler_name, 'Handler', true)) { //php5.0.3+
            $obj = new $handler_name($route['module'], $method);
        }
        else if (function_exists($handler_name)) {
            $obj = new self($route['module'], $handler_name);
        }
        return $obj;
    }
    
    public function prepare(array $args=array())
    {
        return $args;
    }

    public function emit(array $args=array())
    {
        if (method_exists($this, $this->callback)) {
            $callback = array($this, $this->callback);
            $args = $this->prepare($args);
        }
        else if (is_callable($this->callback)) {
            $callback = $this->callback;
            array_unshift($args, $this->app);
        }
        if (isset($callback)) {
            return Procedure::exec($callback, $args);
        }
    }
}


/**
 * 模板引擎
 */
class Templater
{
    public $cacher = null;
    public $template_dir = '';
    public $globals = array();
    private $extend_files = array();
    private $template_blocks = array();
    private $current_block = '';
    private $current_cached = false;

    public function __construct($template_dir, array $globals=array(), $cacher=null)
    {
        $this->template_dir = rtrim($template_dir, DS);
        $this->globals = $globals;
        $this->globals['current_url'] = app()->router->current_url;
        $this->cacher = $cacher;
    }
    
    public function urlFor($url, array $args=null, $reverse=false)
    {
        $router = app()->router;
        if ($url === '' || $url === $router->current_url) {
            return $router->urlForCurrent($args, $reverse);
        }
        return $url;
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
       代替 $this->includeTpl($template_file); */
    public function includeTpl($template_file, array $context=array(), $cached=false)
    {
        $include_html = '';
        if ($cached && $this->cacher) {
            $include_name = basename($template_file, '.html');
            $include_html = $this->cacher->get($include_name, '');
        }
        if (empty($include_html)) {
            extract($this->globals);
            extract($context);
            ob_start();
            include $this->template_dir . DS . $template_file;
            $include_html = trim(ob_get_clean());
        }
        if ($cached && $this->cacher) {
            $this->cacher->set($include_name, $include_html);
        }
        echo $include_html;
    }

    public function extendTpl($template_file)
    {
        array_push($this->extend_files, $template_file);
    }

    public function blockStart($block_name='content', $cached=false)
    {
        $this->current_block = $block_name;
        $this->current_cached = $cached;
        $block_html = '';
        if ($cached && $this->cacher) {
            $block_html = $this->cacher->get($block_name, '');
            if ($block_html) {
                $this->template_blocks[$this->current_block] = $block_html;
            }
        }
        if (empty($block_html)) {
            ob_start();
        }
    }

    public function blockEnd()
    {
        if (! isset($this->template_blocks[$this->current_block])) {
            $block_html = trim(ob_get_clean());
            $this->template_blocks[$this->current_block] = $block_html;
            if ($this->current_cached && $this->cacher) {
                $this->cacher->set($this->current_block, $block_html);
            }
        }
    }
}
