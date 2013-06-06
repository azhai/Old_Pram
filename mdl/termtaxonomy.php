<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */

use \Pram\Model;


/**
 * 类别、标签
 */
final class Term extends Model
{
    const PKEY_FIELD = 'term_id';
}


final class TermTaxonomy extends Model
{
    const PKEY_FIELD = 'term_taxonomy_id';
}

