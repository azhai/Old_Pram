<?php


function home_page($app, $page=1)
{
    $page_length = 10;
    $offset = (intval($page) - 1) * $page_length;
    $length = $page_length + 1;
        
    $coll = $app->register->get('article@\Pram\Collection');
    $articles = $coll->load(
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


function article_show($app, $id)
{
    $coll = $app->register->get('article@\Pram\Collection');
    $article = $coll->get($id);
    return $app->templater->render('article/show.html', array(
        'article' => $article,
    ));
}


$app->route('/', 'home_page', array('GET'));
$app->route('/<int>/', 'home_page', array('GET'));
$app->route('/article/<int>/', 'article_show', array('GET'));
