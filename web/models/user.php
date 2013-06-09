<?php
/**
 * Project CallPal (http://www.callpal.com)
 *
 * @copyright 2013 (HK) Alicall Technology Ltd.
 * @author Ryan Liu <azhai@126.com>
 */


class UserCollection extends Collection
{
    //当前用户
    public function current()
    {
    }
}


final class UserMeta extends Model
{
    const PKEY_FIELD = 'umeta_id';
}


/**
 * 用户
 */
final class User extends Model
{
    const PKEY_FIELD = 'ID';
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
        return date_create($this->user_registered ? $this->user_registered : '2000-01-01');
    }
}

