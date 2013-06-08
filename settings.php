<?php
defined('APP_ROOT') or die();

return array(
    'error_log' => APP_ROOT . '/logs/error.log',
    'search_pathes' => array(
        WEB_ROOT . '/views',
        WEB_ROOT . '/models',
    ),
    'router' => 'HamRouter',
    'logger' => array('KFileLogger', false, APP_ROOT . '/logs/'),
    'templater' => array(
        'Templater',
        WEB_ROOT . '/templates/',
        APP_ROOT . '/tmp/',
        'globals' => array(
            'site_url' => '',
            'static_url' => '/static',
        ),
    ),
    'pdo' => array(
        'default' => array(
            'class' => 'PDO',
            'dsn' => 'mysql:host=localhost;dbname=db_account',
            'user' => 'dba',
            'password' => 'changeme',
            'options' => array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'", //指定字符编码utf8
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //错误模式改为抛出异常，而不是slient
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
            ),
        ),
        'test' => array(
            'class' => 'PDO',
            'dsn' => 'mysql:host=localhost;dbname=db_wordpress'
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
    'taxonomies' => array(
        'class' => 'Collection',
        '#db' => '',
        'table' => 'term_taxonomy',
        'model' => 'TermTaxonomy',
    ),
    'users' => array(
        'class' => 'UserCollection',
        '#db' => '',
        'table' => 'users',
        'model' => 'User',
    ),
);
