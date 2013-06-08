<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


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
    
    public function getTerm()
    {
        $coll = new Collection(app()->db, 'terms', 'Term');
        $term = $coll->get($this->term_id);
        return empty($term) ? new Term(): $term;
    }
}

