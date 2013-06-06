<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));
defined('SETTINGS_FILE') or define('SETTINGS_FILE', APP_ROOT . '/settings.php');


require APP_ROOT . '/lib/autoload.php';
require APP_ROOT . '/web/misc.php';
$envname = 'test';

$app = \Pram\Register::createApp($envname);
require __DIR__ . '/' . $envname . '.php';
$app->logger = $app->register->get('\KFileLogger');
$app->db = $app->register->get('\Pram\Database');
$app->templater = $app->register->get('\Pram\Templater');

/*博客配置项*/
$app->templater->globals['options'] = $app->db->doSelect(
    'wp_options', 'WHERE option_name in (?, ?, ?, ?)',
    array('siteurl', 'blogname', 'blogdescription', 'posts_per_page'),
    'option_name, option_value', 
    PDO::FETCH_COLUMN | PDO::FETCH_GROUP | PDO::FETCH_UNIQUE
);
/*最近文章*/
$article_conds = array('post_type'=>'post', 'post_status'=>'publish');
$coll = new \Pram\Collection($app->db, 'posts');
$app->templater->globals['recent_articles'] = $coll->load($article_conds, 'ORDER BY post_date DESC LIMIT 5');
/*最近评论*/
$coll = new \Pram\Collection($app->db, 'comments', 'Comment');
$app->templater->globals['recent_comments'] = $coll->load(array('comment_type'=>''), 'ORDER BY comment_date DESC LIMIT 5');
/*文章归档*/
$coll = new \Pram\Collection($app->db, 'posts');
$app->templater->globals['archives'] = $coll->load($article_conds, 'GROUP BY YEAR(post_date), MONTH(post_date) DESC LIMIT 5');
/*文章分类*/
$coll = new \Pram\Collection($app->db, 'term_taxonomy', 'TermTaxonomy');
$app->templater->globals['categories'] = $coll->load(array('taxonomy'=>'category'));

$app->run();