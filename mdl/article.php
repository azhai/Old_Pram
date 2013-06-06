<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */

use \Pram\Model;


final class ArticleMeta extends Model
{
    const PKEY_FIELD = 'meta_id';
}


/**
 * 文章
 */
final class Article extends Model
{
    private $post_date = null;
    private $post_date_gmt = null;
    private $post_modified = null;
    private $post_modified_gmt = null;
    
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
        return $this->post_author;
        global $app;
        $coll = $app->register->get('user@\Pram\Collection');
        $author = $coll->get($this->post_author);
        return $author ? $author->display_name : '';
    }
    
    public function getComments()
    {
        return array();
        global $app;
        $coll = $app->register->get('comment@\Pram\Collection');
        $comments = $coll->load(array('comment_post_id'=>$this->id, 'comment_approved'=>1));
        return $comments;
    }
    
    public function getCategories()
    {
        return array();
    }
    
    public function getTags()
    {
        return array();
    }
}

