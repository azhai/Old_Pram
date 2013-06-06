<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */

use \Pram\Model;


final class CommentMeta extends Model
{
    const PKEY_FIELD = 'meta_id';
}


/**
 * 评论
 */
final class Comment extends Model
{
    const PKEY_FIELD = 'comment_id';
}

