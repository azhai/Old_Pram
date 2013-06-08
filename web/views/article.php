<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */


function article_show($id)
{
    $app = app();
    $article = $app->articles->get($id);
    return $app->templater->render('article/show.html', array(
        'article' => $article,
    ));
}
