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
    //const PKEY_FIELD = 'ID';
    private $post_date = null;
    private $post_date_gmt = null;
    private $post_modified = null;
    private $post_modified_gmt = null;
    
    //保存前操作
    public function beforeSave()
    {
        if (empty($this->ID)) {
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
            'comment_post_id'=>$this->ID, 'comment_approved'=>1,
        ));
        return $comments;
    }
    
    public function getCategories()
    {
        $app = app();
        $term_taxonomy_ids = $app->db->doSelect(
            'wp_term_relationships', 'WHERE object_id=? ORDER BY term_order', 
            array($this->ID), 'term_taxonomy_id', PDO::FETCH_COLUMN
        );
        $taxonomies = $app->taxonomies->load(array(
            'term_taxonomy_id'=>$term_taxonomy_ids, 'taxonomy'=>'category',
        ));
        $terms = array();
        foreach ($taxonomies as $taxonomy) {
            array_push($terms, $taxonomy->term);
        }
        return $terms;
    }
    
    public function getTags()
    {
        $app = app();
        $term_taxonomy_ids = $app->db->doSelect(
            'wp_term_relationships', 'WHERE object_id=? ORDER BY term_order', 
            array($this->ID), 'term_taxonomy_id', PDO::FETCH_COLUMN
        );
        $taxonomies = $app->taxonomies->load(array(
            'term_taxonomy_id'=>$term_taxonomy_ids, 'taxonomy'=>'post_tag',
        ));
        $terms = array();
        foreach ($taxonomies as $taxonomy) {
            array_push($terms, $taxonomy->term);
        }
        return $terms;
    }
}

