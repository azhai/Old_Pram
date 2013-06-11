<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


final class CommentMeta extends Model
{
    const PKEY_FIELD = 'meta_id';
}


/**
 * 评论
 */
final class Comment extends Model
{
    const PKEY_FIELD = 'comment_ID';
    
    public function getArticle()
    {
        $article = app()->articles->get($this->comment_post_ID);
        return $article ? $article : new Article();
    }
}


class CommentListener extends Listener
{
    public $names = array('article');
    
    public function afterLoad(array& $comments)
    {
        $get_article_id = create_function('$obj', 'return $obj->comment_post_ID;');
        $article_ids = array_map($get_article_id, $comments);
        if (count($article_ids) > 0) {
            $coll = new Collection(app()->db, 'posts', 'Article');
            $articles = $coll->load(array(Article::PKEY_FIELD => $article_ids));
            foreach ($comments as & $comment) {
                $comment->article = $articles[$comment->comment_post_ID];
            }
        }
        return $comments;
    }
}

