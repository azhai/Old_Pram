<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */

use \Pram\Model;


final class UserMeta extends Model
{
    const PKEY_FIELD = 'umeta_id';
}


/**
 * 用户
 */
final class User extends Model
{
    private $user_registered = null;
    private $user_status = 0;
    
    //保存前操作
    public function beforeSave()
    {
        if (empty($this->id)) {
            $this->user_registered = date('Y-m-d H:i:s');
        }
    }
    
    public function getUserRegistered()
    {
        return date_create($this->user_registered : '2000-01-01');
    }
}

