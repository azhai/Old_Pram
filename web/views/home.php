<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */


/**
 * 首页，最近博客列表
 */
function home_page($page=1)
{
    $page_length = 10;
    $offset = (intval($page) - 1) * $page_length;
    $length = $page_length + 1;
    
    $app = app();
    $articles = $app->articles->load(
        array('post_type'=>'post', 'post_status'=>'publish'),
        "ORDER BY post_date DESC LIMIT $offset,$length"
    );
    $next_page = count($articles) > $page_length;
    if ($next_page) {
        unset($articles[$page_length]);
    }
    return $app->templater->render('home/index.html', array(
        'articles' => $articles, 'page' => $page, 'next_page' => $next_page,
    ));
}