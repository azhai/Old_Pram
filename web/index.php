<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));
defined('SETTINGS_FILE') or define('SETTINGS_FILE', APP_ROOT . '/settings.php.php');


require APP_ROOT . '/lib/autoload.php';
require APP_ROOT . '/lib/utils/ip.php';
$app = \Pram\Register::init('test');
@error_log($app->getSettings('error_log'));
@session_start();
