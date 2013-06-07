<?php
defined('APP_URI') or define('APP_URI', '');


class HamRouter 
{
    public $register;
    public $routes;
    public $name;
    public $cache;
    public $parent;
    public $prefix;

    /**
     * Create a Ham application.
     * @param string $name a canonical name for this app. Must not be shared between apps or cache collisions will happen. Unless you want that.
     * @param mixed $cache
     * @param bool $log
     */
    public function __construct($name='default', $cache=False) 
    {
        $this->name = $name;
        if($cache === False) {
            $cache = static::create_cache($this->name);
        }
        $this->cache = $cache;
    }

    /**
     * Add routes
     * @param $uri
     * @param $callback
     * @param array $request_methods
     * @return bool
     */
    public function route($uri, $callback, $request_methods=array('GET')) 
    {
        if($this === $callback) {
            return False;
        }
        $wildcard = False;
        if($callback instanceof Ham) {
            $callback->prefix = $uri;
            $wildcard = True;
        }

        $this->routes[] = array(
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods,
            'wildcard' => $wildcard
        );

        return true;
    }

    /**
     * Calls route and outputs it to STDOUT
     */
    public function run() 
    {
        echo $this();
    }

    /**
     * Invoke method allows the application to be mounted as a closure.
     * @param mixed|bool $app parent application that can be referenced by $app->parent
     * @return mixed|string
     */
    public function __invoke($app=False) 
    {
        $this->parent = $app;
        return $this->_route($_SERVER['REQUEST_URI']);
    }

    /**
     * Makes sure the routes are compiled then scans through them
     * and calls whichever one is approprate.
     */
    protected function _route($request_uri) 
    {
        $uri = parse_url(str_replace(APP_URI, '', $request_uri));
        $path = $uri['path'];
        $_k = "found_uri:{$path}";
        $found = $this->cache->get($_k);
        if(!$found) {
            $found = $this->_find_route($path);
            $this->cache->set($_k, $found, 10);
        }
        if(!$found) {
            return static::abort(404);
        }
        $found['args'][0] = $this;
        return call_user_func_array($found['callback'], $found['args']);
    }


    protected function _find_route($path) 
    {
        $compiled = $this->_get_compiled_routes();
        foreach($compiled as $route) {
            if(preg_match($route['compiled'], $path, $args)) {
                $found = array(
                    'callback' => $route['callback'],
                    'args' => $args
                );
                return $found;
            }
        }
        return False;
    }

    protected function _get_compiled_routes() 
    {
        $_k = 'compiled_routes';
        $compiled = $this->cache->get($_k);
        if($compiled)
            return $compiled;

        $compiled = array();
        foreach($this->routes as $route) {
            $route['compiled'] = $this->_compile_route($route['uri'], $route['wildcard']);
            $compiled[] = $route;
        }
        $this->cache->set($_k, $compiled);
        return $compiled;
    }

    /**
     * Takes a route in simple syntax and makes it into a regular expression.
     */
    protected function _compile_route($uri, $wildcard) 
    {
        $route = $this->_escape_route_uri(rtrim($uri, '/'));
        $types = array(
            '<int>' => '([0-9\-]+)',
            '<float>' => '([0-9\.\-]+)',
            '<string>' => '([a-zA-Z0-9\-_]+)',
            '<path>' => '([a-zA-Z0-9\-_\/])'
        );
        foreach($types as $k => $v) {
            $route =  str_replace(preg_quote($k), $v, $route);
        }
        if($wildcard)
            $wc = '(.*)?';
        else
            $wc = '';
        $ret = '/^' . $this->_escape_route_uri($this->prefix) . $route . '\/?' . $wc . '$/';
        return  $ret;
    }

    protected function _escape_route_uri($uri) 
    {
        return str_replace('/', '\/', preg_quote($uri));
    }

    /**
     * Cancel application
     * @param $code
     * @param string $message
     * @return string
     */
    public static function abort($code, $message='') 
    {
        if(php_sapi_name() != 'cli')
            header("Status: {$code}", False, $code);
        return "<h1>{$code}</h1><p>{$message}</p>";
    }

    /**
     * Cache factory, be it XCache or APC.
     */
    public static function create_cache($prefix, $dummy=False) 
    {
        return new Dummy($prefix);
    }
}

class Dummy extends HamCache {
    public function get($key) {
        return False;
    }
    public function set($key, $value, $ttl=1) {
        return False;
    }
    public function inc($key, $interval=1) {
        return False;
    }
    public function dec($key, $interval=1) {
        return False;
    }
}

abstract class HamCache {
    public $prefix;

    public function __construct($prefix=False) {
        $this->prefix = $prefix;
    }
    protected function _p($key) {
        if($this->prefix)
            return $this->prefix . ':' . $key;
        else
            return $key;
    }
    abstract public function set($key, $value, $ttl=1);
    abstract public function get($key);
    abstract public function inc($key, $interval=1);
    abstract public function dec($key, $interval=1);
}

