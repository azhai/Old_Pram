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


final class Taxonomy extends Model
{
    const PKEY_FIELD = 'term_taxonomy_id';
    public $foreigns = array('term'=>null);
    
    public function getTerm()
    {
        $coll = new Collection(app()->db, 'terms', 'Term');
        $term = $coll->get($this->term_id);
        return empty($term) ? new Term(): $term;
    }
}


class TaxonomyListener extends Listener
{
    public $names = array('term');
    
    public function afterLoad(array& $taxonomies)
    {
        $get_term_id = create_function('$obj', 'return $obj->term_id;');
        $term_ids = array_map($get_term_id, $taxonomies);
        if (count($term_ids) > 0) {
            $coll = new Collection(app()->db, 'terms', 'Term');
            $terms = $coll->load(array(Term::PKEY_FIELD => $term_ids));
            foreach ($taxonomies as & $taxonomy) {
                $taxonomy->term = $terms[$taxonomy->term_id];
            }
        }
        return $taxonomies;
    }
}

