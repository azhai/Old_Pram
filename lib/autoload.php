<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */
 
namespace Pram;
use \PDO as PDO;

defined('SETTINGS_FILE') or define('SETTINGS_FILE', 'settings.php');
defined('DEFAULT_TIMEZONE') or define('DEFAULT_TIMEZONE', 'Asia/Shanghai');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
@date_default_timezone_set(DEFAULT_TIMEZONE);


/**
 * 全局注册表
 * 用于自动加载类文件，根据配置生成类对象
 */
class Register
{
    public static $autoload_classes = array( //加载类列表
        'HamRouter' =>  'router.php',
        'Pram\Database' =>  'database.php',
        'Pram\Collection' => 'database.php',
        'Pram\SQLiteral' => 'database.php',
        'Pram\Templater' =>  'templater.php',
        'Pram\HTTPClient' =>  'httpclient.php',
        'KLogger' =>  'logger.php',
        'KFileLogger' =>  'logger.php',
        'KHTTPLogger' =>  'logger.php',
        'KPDOLogger' =>  'logger.php',
    );
    protected static $instances = array(); //实例
    protected static $settings = array(); //配置
    protected static $search_pathes = array(); //类搜索路径
    protected $services = array(); //服务
    protected $envname = '';
    protected $settings_parsed = false;
    
    protected function __construct($envname)
    {
        $this->envname = $envname;
    }

    //初始化
    public static function createApp($envname='default', $router='\HamRouter')
    {
        if (! isset(self::$instances[$envname])) { //创建实例
            $current_class = get_called_class();
            $env = new $current_class($envname);
            self::$instances[$envname] = $env;
        }
        $instance = self::$instances[$envname];
        if ($error_log = $instance->getSettings('error_log')) {
            @error_log($error_log); //错误日志
        }
        $app = is_string($router) ? $instance->get($router) : $router;
        $app->register = $instance;
        return $app;
    }

    //自动加载
    public static function autoload($class)
    {
        if (array_key_exists($class, self::$autoload_classes)) {
            require_once __DIR__ . '/' . self::$autoload_classes[$class];
            return true;
        }
        foreach (self::$search_pathes as $search_path) {
            $php_file = rtrim($search_path, '/') . '/' . strtolower($class) . '.php';
            if (file_exists($php_file)) {
                require_once $php_file;
                if (class_exists($class, false)) {
                    return true;
                }
            }
        }
    }

    public function parseSettings($name)
    {
        if (array_key_exists('\PDO', self::$settings)) {
            foreach (self::$settings['\PDO'] as $key => & $settings) {
                if (substr(strtolower($settings['dsn']), 0, 5) == 'mysql') {
                    $options = array(
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'", //指定字符编码utf8
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //错误模式改为抛出异常，而不是slient
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    );
                }
                else {
                    $options = array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //错误模式改为抛出异常，而不是slient
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    );
                }
                if (! isset($settings['options'])) {
                    $settings['options'] = $options;
                }
                else {
                    $settings['options'] = array_merge($settings['options'], $options);
                }
            }
        }
        $this->settings_parsed = true;
    }

    public function getSettings($name, $subname='', $type='array')
    {
        if (! $this->settings_parsed) {
            if (empty(self::$settings)) { //加载配置
                $settings = (include SETTINGS_FILE);
                self::$settings = ($settings === false) ? array() : $settings;
                if (isset(self::$settings['search_pathes'])) {
                    self::$search_pathes += self::$settings['search_pathes'];
                }
                spl_autoload_register(__CLASS__ . '::autoload');
            }
            $this->parseSettings($name);
        }
        if (! array_key_exists($name, self::$settings)) {
            return null;
        }
        $all_settings = self::$settings[$name];
        //默认配置
        if (array_key_exists('default', $all_settings)) {
            $default_settings = $all_settings['default'];
        }
        else {
            $default_settings = ($type === 'array') ? array() : (($type === 'string') ? '' : 0);
        }
        //与默认配置合并
        $subname = empty($subname) ? $this->envname : $subname;
        if ($subname !== 'default' && array_key_exists($subname, $all_settings)) {
            if ($type === 'array') {
                return array_merge($default_settings, $all_settings[$subname]);
            }
            else {
                return $all_settings[$subname];
            }
        }
        return $default_settings;
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
        $names = explode('@', $name, 2);
        list($subname, $name) = count($names) == 2 ? $names : array('', $names[0]);
        $args = $this->getSettings($name, $subname, 'array');
        if (is_array($args)) {
            foreach ($args as $key => & $arg) {
                if (substr($key, 0, 1) === '#') {
                    $arg = $this->get($arg);
                }
            }
            //$name = str_replace(__NAMESPACE__ . '\\', '', $name);
            $ref = new \ReflectionClass($name);
            return $ref->newInstanceArgs($args);
        }
    }

    public function & get($name)
    {
        if (! isset($this->services[$name])) {
            $this->putService($this->getService($name), $name);
        }
        return $this->services[$name];
    }
}

