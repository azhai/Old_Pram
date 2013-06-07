<?php
defined('APP_ROOT') or die();

return array(
    'search_pathes' => array(
        APP_ROOT . '/mdl',
    ),
    'error_log' => array(
        APP_ROOT . '/logs/error/php.log',
    ),
    '\HamRouter' => array(
        'default' => array(
            'name' => 'default',
        ),
        'test' => array(
            'name' => 'test',
        ),
    ),
    '\KFileLogger' => array(
        'default' => array(
            'severity' => false,
            'directory' => APP_ROOT . '/logs/default/',
        ),
        'test' => array(
            'severity' => false,
            'directory' => APP_ROOT . '/logs/test/',
        ),
    ),
    '\Pram\Templater' => array(
        'default' => array(
            'template_dir' => APP_ROOT . '/tpl/',
            'cache_dir' => APP_ROOT . '/tmp/',
            'globals' => array(
                'site_url' => '',
                'static_url' => '/static',
            ),
        ),
    ),
    '\PDO' => array(
        'default' => array(
            'dsn' => 'mysql:host=localhost;dbname=db_account',
            'user' => 'dba',
            'password' => 'changeme',
        ),
        'test' => array('dsn' => 'mysql:host=localhost;dbname=db_wordpress'),
    ),
    '\Pram\Database' => array(
        'test' => array(
            '#pdo' => '\PDO',
            'table_prefix'=>'wp_',
        ),
    ),
    '\Pram\Collection' => array(
        'article' => array(
            '#db' => '\Pram\Database',
            'table' => 'posts',
            'model' => 'Article',
        ),
        'comment' => array(
            '#db' => '\Pram\Database',
            'table' => 'comments',
            'model' => 'Comment',
        ),
        'user' => array(
            '#db' => '\Pram\Database',
            'table' => 'users',
            'model' => 'User',
        ),
    ),
);
