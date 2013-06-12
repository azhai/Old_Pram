<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


/**
 * 缓存
 */
abstract class Cacher
{
    public $prefix = false;
    public $backends = array();

    public function __construct() 
    {
        $args = func_get_args();
        if (count($args) > 0) {
            $this->prefix = array_shift($args);
            $this->backends = $args;
        }
    }
    
    public function slug($key)
    {
        return empty($this->prefix) ? $key : $this->prefix . $key;
    }
    
    public function retrieve($key, $callback, $ttl=0) 
    {
        $value = $this->get($key);
        if (is_null($value) || $value === false) {
            if ($callback instanceof Procedure) {
                $value = $callback->invoke($key);
            }
            else {
                $value = Procedure::exec($callback, array($key));
            }
            $this->set($key, $value, $ttl);
        }
        return $value;
    }
    
    public function inc($key, $interval=1)
    {
        $key = $this->slug($key);
        if ($key !== false) {
            $value = $this->get($key, 0);
            if (is_numeric($value)) {
                $value = intval($value) + intval($interval);
                $this->set($key, $value);
                if ($value >= 0) { //检查是否自然数或向上溢出
                    $this->set($key, $value);
                    return $value;
                }
            }
        }
        return false;
    }
    
    public function dec($key, $interval=1)
    {
        return $this->inc($key, -abs($interval));
    }
    
    public function __call($method, $args)
    {
        if (! in_array($method, array('get', 'set', 'delete'))) {
            return;
        }
        $key = $this->slug($key);
        if ($key === false) {
            return false;
        }
        //私有函数，只能在类内部调用
        $result = call_user_func_array(array($this, '_' . $method), $args);
        if (is_null($result) || $result === false) {
            foreach ($this->backends as $backend) {
                $result = Procedure::exec(array($backend, $method), $args);
                if (! is_null($result) && $result !== false) {
                    break;
                }
            }
        }
        return $result;
    }
    
    abstract protected function _get($key, $default=null);
    abstract protected function _set($key, $value, $ttl=0);
    abstract protected function _delete($key);
}


/**
 * 虚拟缓存，随PHP进程一起消失
 */
class DummyCacher extends Cacher
{
    public static $storage = array();
    
    protected function _get($key, $default=null)
    {
        if ($key !== false && array_key_exists($key, self::$storage)) {
            return self::$storage[$key];
        }
        else {
            return $default;
        }
    }
    
    protected function _set($key, $value, $ttl=0)
    {
        if ($key !== false) {
            self::$storage[$key] = $value;
            return true;
        }
        return false;
    }
    
    protected function _delete($key)
    {
        if ($key !== false) {
            unset(self::$storage[$key]);
            return true;
        }
        return false;
    }
}


/**
 * 文件缓存
 */
class FileCacher extends Cacher
{
    protected $directory = '';
    protected $file_ext = '.html';
    protected $file_mode = 0666;
    
    public function __construct() 
    {
        $args = func_get_args();
        $params = array_shift($args);
        $directory = dirname(__FILE__);
        if (is_string($params)) {
            $this->directory = $params;
        }
        else if (is_array($params) && count($params) >= 3) {
            @list($this->directory, $this->file_ext, $this->file_mode) = $params;
        }
        //递归创建目录
        $this->directory = rtrim(str_replace('/', DS, $this->directory), DS);
        @mkdir($this->directory, $this->file_mode, true);
        if (count($args) > 0) {
            $this->prefix = array_shift($args);
            $this->backends = $args;
        }
    }
    
    public function slug($key)
    {
        return str_replace(
            array(' ', '?', '*', '/', '\\'),
            array('', '', '', '-', '-'),
            parent::slug($key)
        );
    }
    
    protected function readFile($filename)
    {
        return file_get_contents($filename);
    }
    
    protected function writeFile($filename, $content, $ttl=0)
    {
        file_put_contents($filename, $content, LOCK_EX);
        return true;
    }
    
    protected function _get($key, $default=null) 
    {
        $filename = $this->directory . DS . $key . $this->file_ext;
        if($key !== false && is_file($filename)) {
            return $this->readFile($filename);
        }
        return $default;
    }
    
    protected function _set($key, $value, $ttl=1) 
    {
        $filename = $this->directory . DS . $key . $this->file_ext;
        try {
            @touch($filename);
            @chmod($filename, $this->file_mode);
            return $this->writeFile($filename, $value, $ttl);
        } 
        catch(Exception $e) {
            return false;
        }
    }
    
    protected function _delete($key)
    {
        $filename = $this->directory . DS . $key . $this->file_ext;
        if ($key !== false && is_file($filename)) {
            @unlink($filename);
            return true;
        }
        return false;
    }
}


/**
 * 序列化文件缓存
 */
class SerializeFileCacher extends FileCacher
{
    protected $directory = '';
    protected $file_ext = '.dat';
    protected $file_mode = 0666;
    
    protected function readFile($filename)
    {
        return unserialize(parent::readFile($filename));
    }
    
    protected function writeFile($filename, $content, $ttl=0)
    {
        return parent::writeFile($filename, serialize($content), $ttl);
    }
}


/**
 * 对象导出缓存
 */
class ExportFileCacher extends FileCacher
{
    protected $directory = '';
    protected $file_ext = '.php';
    protected $file_mode = 0666;
    
    protected function readFile($filename)
    {
        return (include $filename);
    }
    
    protected function writeFile($filename, $content, $ttl=0)
    {
        $content = "<?php \nreturn " . var_export($content, true) . ";\n";
        return parent::writeFile($filename, $content, $ttl);
    }
}


/**
 * APC缓存
 */
class APCacher extends Cacher
{
    private $active = false;
    
    public function __construct() 
    {
        $args = func_get_args();
        if (extension_loaded('apc') && ini_get('apc.enabled') == '1') {
            $this->active = true;
        }
        if (count($args) > 0) {
            $this->prefix = array_shift($args);
            $this->backends = $args;
        }
    }
    
    public function slug($key)
    {
        if ($this->active === false) {
            return false;
        }
        else {
            return parent::slug($key);
        }
    }
    
    protected function _get($key, $default=null) 
    {
        if(apc_exists($key)) {
            return apc_fetch($key);
        }
        return $default;
    }
    
    protected function _set($key, $value, $ttl=1) 
    {
        try {
            return apc_store($key, $value, $ttl);
        } 
        catch(Exception $e) {
            apc_delete($key);
            return false;
        }
    }
    
    protected function _delete($key)
    {
        if ($key !== false) {
            apc_delete($key);
            return true;
        }
        return false;
    }
    
    public function inc($key, $interval=1) 
    {
        $key = $this->slug($key);
        if ($key !== false) {
            return apc_inc($key, $interval);
        }
        return false;
    }
    
    public function dec($key, $interval=1) 
    {
        $key = $this->slug($key);
        if ($key !== false) {
            return apc_dec($key, abs($interval));
        }
        return false;
    }
}


/**
 * Redis缓存
 */
class RedisCacher extends Cacher
{
    private $redis = null;
    
    public function __construct() 
    {
        $args = func_get_args();
        if (extension_loaded('redis')) {
            $this->redis = new Redis();
            $params = array_shift($args);
            $host = '127.0.0.1';
            $port = 6379;
            if (is_string($params)) {
                $host = $params;
                $this->redis->connect($host, $port);
            }
            else if (is_array($params)) {
                call_user_func_array(array($this->redis, 'connect'), $params);
            }
        }
        if (func_num_args() > 0) {
            $this->prefix = array_shift($args);
            $this->backends = $args;
        }
    }
    
    protected function _get($key, $default=null) 
    {
        return $this->redis->hGetAll($key);
    }
    
    protected function _set($key, $value, $ttl=1) 
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            return $this->redis->hmSet($key, $value);
        }
    }
    
    protected function _delete($key)
    {
        if ($key !== false) {
            $this->redis->del($key);
            return true;
        }
        return false;
    }
    
    public function inc($key, $interval=1) 
    {
        $key = $this->slug($key);
        return $this->redis->incrBy($key, $interval);
    }
    
    public function dec($key, $interval=1) 
    {
        $key = $this->slug($key);
        return $this->redis->decrBy($key, abs($interval));
    }
}