<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


/**
 * 监听者
 */
class Listener
{
    protected $subject = null;

    public function wrap($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function unwrap()
    {
        return $this->subject;
    }

    public function __call($method, $args)
    {
        $before_method = 'before' . $method;
        $after_method = 'after' . $method;
        if (method_exists($this, $before_method)) {
            $args = $this->$before_method($args); //前置触发
        }
        $result = Procedure::exec(array($this->subject, $method), $args);
        if (method_exists($this, $after_method)) {
            $result = $this->$after_method($result); //后置触发
        }
        return $result;
    }
}


/**
 * 观察者
 */
class Observer extends Listener
{
    protected $listeners = array();
    protected $events = array();
    
    public function listen()
    {
    }
    
    public function notify()
    {
    }
    
    public function __call($method, $args)
    {
        $result = parent::__call($method, $args);
        return $result;
    }
    
    public function __get($name)
    {
        $result = null;
        if (property_exists($this->subject, $name)) {
            $result = $this->subject->$name;
        }
        return $result;
    }
    
    public function __set($name, $value)
    {
        $result = false;
        if (property_exists($this->subject, $name)) {
            $this->subject->$name = $value;
            $result = true;
        }
        return $result;
    }
}
