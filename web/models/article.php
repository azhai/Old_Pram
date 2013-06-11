<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


final class ArticleMeta extends Model
{
    const PKEY_FIELD = 'meta_id';
}


/**
 * 文章
 */
final class Article extends Model
{
    const PKEY_FIELD = 'ID';
    private $post_date = null;
    private $post_date_gmt = null;
    private $post_modified = null;
    private $post_modified_gmt = null;
    public $foreigns = array(
        'author'=>null, 'comments'=>array(),
        'categories'=>array(), 'tags'=>array(),
    );
    
    //保存前操作
    public function beforeSave()
    {
        if (empty($this->id)) {
            $this->post_date = $this->post_date_gmt = date('Y-m-d H:i:s');
        }
        else {
            $this->post_modified = $this->post_modified_gmt = date('Y-m-d H:i:s');
        }
    }
    
    public function getPostDate()
    {
        return date_create($this->post_date ? $this->post_date : '2000-01-01');
    }
    
    public function getPostModified()
    {
        return date_create($this->post_modified ? $this->post_modified : '2000-01-01');
    }
    
    public function getAuthor()
    {
        $author = app()->users->get($this->post_author);
        return $author ? $author : new User();
    }
    
    public function getComments()
    {
        $comments = app()->comments->load(array(
            'comment_post_id'=>$this->id, 'comment_approved'=>1,
        ));
        return $comments;
    }
    
    public function getTaxonomies()
    {
        $app = app();
        $article_id = $this->id;
        $subquery = "SELECT term_taxonomy_id FROM `wp_term_relationships`"
                  . " WHERE object_id='$article_id'";
        $taxonomies = $app->taxonomies->with(new TermListener())->load(
            array(), "WHERE term_taxonomy_id IN ($subquery)"
        );
        $categories = array();
        $tags = array();
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->taxonomy === 'category') {
                array_push($categories, $taxonomy);
            }
            else {
                array_push($tags, $taxonomy);
            }
        }
        $this->categories = $categories;
        $this->tags = $tags;
        return $taxonomies;
    }
    
    public function getCategories()
    {
        $this->getTaxonomies();
        return $this->categories;
    }
    
    public function getTags()
    {
        $this->getTaxonomies();
        return $this->tags;
    }
}


class ArticleListener extends Listener
{
    public $names = array();
    
    public function __construct()
    {
        $this->names = func_get_args();
    }
    
    public function afterLoad(array& $articles)
    {
        $app = app();
        if (in_array('author', $this->names)) { //belongsTo
            $get_author_id = create_function('$obj', 'return $obj->post_author;');
            $author_ids = array_map($get_author_id, $articles);
            $authors = $app->users->load(array('ID'=>$author_ids));
        }
        if (in_array('comments', $this->names) 
                    || in_array('categories', $this->names) 
                    || in_array('tags', $this->names)) {
            $article_ids = array_keys($articles);
            if (in_array('comments', $this->names)) { //oneToMany
                $_comments = $app->comments->load(array('comment_post_ID'=>$article_ids));
                $comments = array();
                foreach ($_comments as $comment) {
                    if (! isset($comments[$comment->comment_post_ID])) {
                        $comments[$comment->comment_post_ID] = array();
                    }
                    array_push($comments[$comment->comment_post_ID], $comment);
                }
            }
            if (in_array('categories', $this->names) || in_array('tags', $this->names)) {
                //manyToMany
                $coll = new Collection($app->db, 'term_relationships');
                $relations = $coll->loadRelations(
                    array('object_id'=>$article_ids), 
                    'object_id', 'term_taxonomy_id'
                );
                $taxonomy_ids = array_reduce($relations, 'array_merge', array());
                $taxonomies = $app->taxonomies->with(new TermListener())->load(
                    array('term_taxonomy_id'=>$taxonomy_ids)
                );
            }
        }
        foreach ($articles as & $article) {
            if (isset($authors)) {
                $article->author = $authors[$article->post_author];
            }
            if (isset($comments)) {
                $article->comments = $comments[$article->id];
            }
            if (isset($taxonomies)) {
                $article->categories = array();
                $article->tags = array();
                foreach ($relations[$article->id] as $taxonomy_id) {
                    $taxonomy = $taxonomies[$taxonomy_id];
                    if ($taxonomy->taxonomy === 'category') {
                        array_push($article->categories, $taxonomy);
                    }
                    else {
                        array_push($article->tags, $taxonomy);
                    }
                }
            }
        }
        return $articles;
    }
}

