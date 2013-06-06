<?php
defined('APP_ROOT') or die();

return array(
    'search_pathes' => array(
        APP_ROOT . '/mdl',
    ),
    'error_log' => array(
        APP_ROOT . '/logs/error/',
    ),
    '\Ham' => array(
        'default' => array(
            'name' => 'default',
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
        'user' => array(
            '#db' => '\Pram\Database',
            'table' => 'users',
            'model' => 'User',
        ),
    ),
);
