<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


/**
 * 监听器
 */
class Listener
{
    protected $subject = null;

    public function wrap($subject)
    {
        $this->subject = $subject;
    }

    public function __call($method, $args)
    {
        $before_method = 'before' . $method;
        $after_method = 'after' . $method;
        if (method_exists($this, $before_method)) {
            $this->$before_method($args); //前置触发
        }
        $result = Procedure::exec(array($this->subject, $method), $args);
        if (method_exists($this, $after_method)) {
            $this->$after_method($result); //后置触发
        }
        return $result;
    }
}

