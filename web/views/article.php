<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

require_once WEB_ROOT . '/models/article.php';
require_once WEB_ROOT . '/models/termtaxonomy.php';


function article_show($app, $id)
{
    $article = $app->articles->with(new TermListener())->get($id);
    return $app->templater->render('article/show.html', array(
        'article' => $article,
    ));
}
