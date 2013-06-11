<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

defined('WEB_ROOT') or define('WEB_ROOT', dirname(__FILE__));
defined('APP_ROOT') or define('APP_ROOT', dirname(WEB_ROOT));


require APP_ROOT . '/library/application.php';
$app = app()->setEnvname('test');

require WEB_ROOT . '/models/user.php';
require WEB_ROOT . '/models/comment.php';
require WEB_ROOT . '/misc.php';


/*路由配置*/
route('/', 'home_page', 'home');
route('/<int>/', 'home_page', 'home');
route('/article/<int>/', 'article_show', null, array('GET'));


/*博客配置项*/
function get_options()
{
    $db = app()->db;
    $options = $db->doSelect(
        'wp_options', 'WHERE option_name in (?, ?, ?, ?)',
        array('siteurl', 'blogname', 'blogdescription', 'posts_per_page'),
        'option_name, option_value',
        PDO::FETCH_COLUMN | PDO::FETCH_GROUP | PDO::FETCH_UNIQUE
    );
    return array(
        'site_title' => $options['blogname'],
        'site_description' => $options['blogdescription'],
        'posts_per_page' => intval($options['posts_per_page']),
    );
}


/*侧边栏*/
function get_sidebar()
{
    $app = app();
    $db = $app->db;
    $sidebar = array();
    $article_conds = array('post_type'=>'post', 'post_status'=>'publish');
    /*最近文章*/
    $coll = new Collection($db, 'posts');
    $sidebar['recent_articles'] = $coll->load($article_conds, 'ORDER BY post_date DESC LIMIT 5');
    /*最近评论*/
    $coll = new Collection($db, 'comments', 'Comment');
    $sidebar['recent_comments'] = $coll->with(new CommentListener())->load(array('comment_type'=>''), 'ORDER BY comment_date DESC LIMIT 5');
    /*文章归档*/
    $coll = new Collection($db, 'posts');
    $sidebar['archives'] = $coll->load($article_conds, 'GROUP BY YEAR(post_date), MONTH(post_date) DESC LIMIT 5');
    /*文章分类*/
    $coll = new Collection($db, 'term_taxonomy', 'TermTaxonomy');
    $sidebar['categories'] = $app->categories->load();
    return $sidebar;
}

$app->templater->globals = array_merge($app->templater->globals, get_options(), get_sidebar());
run($app);
