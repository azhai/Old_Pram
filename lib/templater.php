<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

namespace Pram;
defined('DS') or define('DS', DIRECTORY_SEPARATOR);


/**
 * PHP原生模板引擎
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

    public function render($template_file, array $context=array())
    {
        $context = array_merge($this->globals, $context);
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

    /* 注意: 必须自己传递context，如果想共享render中的context，请在模板中
       使用 include $this->template_dir . DS . $template_file; 
       代替 $this->include_tpl($template_file); */
    public function include_tpl($template_file, array $context=array())
    {
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
