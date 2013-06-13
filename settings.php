<?php
defined('APP_ROOT') or die();

return array(
    'error_log' => APP_ROOT . '/logs/error.log',
    'search_pathes' => array(
        WEB_ROOT . '/models',
        WEB_ROOT . '/views',
    ),
    'router' => 'HamRouter',
    'logger' => array('KFileLogger', false, APP_ROOT . '/logs/'),
    'dblogger' => array('KFileLogger', 3, APP_ROOT . '/logs/', array(
        'headname' => 'dblog_', 'extname' => '.log',
    )),
    'templater' => array(
        'Templater',
        WEB_ROOT . '/templates/',
        'globals' => array(
            'site_url' => '',
            'static_url' => '/static',
        ),
        '#tplcacher' => '',
    ),
    'redis' => array('RedisCacher', array('127.0.0.1', 6379), 'dat.'),
    'tplcacher' => array('FileCacher', array(APP_ROOT . '/tmp/', '.html', 0666), 'tpl.'),
    'pdo' => array(
        'default' => array(
            'class' => 'PDO',
            'dsn' => 'mysql:host=localhost;dbname=db_account;charset=utf8',
            'user' => 'dba',
            'password' => 'changeme',
            'options' => array(
                #PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'", //指定字符编码utf8
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //错误模式改为抛出异常，而不是slient
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
            ),
        ),
        'test' => array(
            'class' => 'PDO',
            'dsn' => 'mysql:host=localhost;dbname=db_wordpress;charset=utf8'
        ),
    ),
    'db' => array(
        'default' => array(
            'class' => 'Database',
            '#pdo' => '',
            'table_prefix'=>'wp_',
        ),
        'test' => array(
        ),
    ),
    'articles' => array(
        'class' => 'Collection',
        '#db' => '',
        'table' => 'posts',
        'model' => 'Article',
    ),
    'comments' => array(
        'class' => 'Collection',
        '#db' => '',
        'table' => 'comments',
        'model' => 'Comment',
    ),
    'users' => array(
        'class' => 'UserCollection',
        '#db' => '',
        'table' => 'users',
        'model' => 'User',
    ),
    'taxonomies' => array(
        'class' => 'Collection',
        '#db' => '',
        'table' => 'term_taxonomy',
        'model' => 'Taxonomy',
    ),
    'categories' => array(
        'class' => 'Collection',
        '#db' => '',
        'table' => 'term_taxonomy',
        'model' => 'Taxonomy',
        'phrases' => array('taxonomy'=>'category'),
    ),
);
