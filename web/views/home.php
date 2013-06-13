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
class HomeHandler extends Handler
{
    public $extra = '';
    
    public function prepare(array $args=array())
    {
        $page = empty($args[0]) ? 1 : intval($args[0]);
        $page_length = intval($args[1]);
        if (empty($page_length)) {
            $page_length = intval($this->app->templater->globals['page_length']);
        }
        return array($page, $page_length);
    }
    
    public function getArticles($offset=0, $length=10)
    {
        $listener = new ArticleListener('author', 'categories', 'tags');
        $articles = $this->app->articles->with($listener)->load(
            array('post_type'=>'post', 'post_status'=>'publish'),
            $this->extra . "ORDER BY post_date DESC LIMIT $offset,$length"
        );
        return $articles;
    }
    
    public function get($page=1, $page_length=10)
    {
        $offset = (intval($page) - 1) * $page_length;
        $length = $page_length + 1;
        $articles = $this->getArticles($offset, $length);
        $next_page = count($articles) > $page_length;
        if ($next_page) {
            unset($articles[$page_length]);
        }
        return $this->app->templater->render('home/index.html', array(
            'articles' => $articles, 'page' => $page, 'next_page' => $next_page,
        ));
    }
}


/**
 * 某分类、标签下的博客列表
 */
class TermHandler extends HomeHandler
{
    public function prepare(array $args=array())
    {
        $term = array_shift($args);
        $this->extra = "AND ID IN (SELECT object_id FROM `wp_term_relationships` WHERE term_taxonomy_id=("
                  . "SELECT term_taxonomy_id FROM `wp_term_taxonomy` WHERE term_id=("
                  . "SELECT term_id FROM `wp_terms` WHERE slug='$term' LIMIT 1) LIMIT 1)) ";
        //exit;
        return parent::prepare($args);
    }
}


/**
 * 某位作者的博客列表
 */
class AuthorHandler extends HomeHandler
{
    public function prepare(array $args=array())
    {
        $author_id = intval(array_shift($args));
        $this->extra = "AND post_author=$author_id ";
        return parent::prepare($args);
    }
}


/**
 * 某个归档下的博客列表
 */
class ArchiveHandler extends HomeHandler
{
    public function prepare(array $args=array())
    {
        $year_month = strval(array_shift($args));
        $year = substr($year_month, 0, 4);
        $month = substr($year_month, 4, 2);
        $this->extra = "AND YEAR(post_date)='$year' AND MONTH(post_date)='$month' ";
        return parent::prepare($args);
    }
}
