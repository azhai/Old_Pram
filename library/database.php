<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */


/**
 * 字面量参数，值不会被脱敏，更新类操作中使用
 * 如传递当前时间作为参数 new SQLiteral('NOW()')
 */
class SQLiteral
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}


/**
 * 数据库，使用PDO连接
 *
 * 使用：
 *     $db->execute($sql, $params)->commit();
 *     $db->query($sql, $params)->fetchAll();
 */
class Database
{
    protected $conn; //当前数据库连接，PDO对象
    protected $stmt; //当前结果集，PDOStatement对象
    public $table_prefix = ''; //当前数据表前缀
    public $lastID = 0;

    public function __construct(PDO $conn, $table_prefix='')
    {
        $this->conn = $conn;
        $this->table_prefix = $table_prefix;
    }

    public function quote($param)
    {
        if ($param instanceof SQLiteral) { //字面量，直接使用
            return $param->value;
        }
        else if (is_null($param)) { //将PHP的null转为MySQL的NULL
            return 'NULL';
        }
        else {
            if (is_bool($param)) { //将PHP的true/false转为MySQL的1/0
                $param_type = PDO::PARAM_BOOL;
            }
            else if (is_int($param)) { //int整数类型、timestamp时间戳
                $param_type = PDO::PARAM_INT;
            }
            else { //字符串、浮点数、日期时间
                $param_type = PDO::PARAM_STR;
            }
            return $this->conn->quote($param, $param_type);
        }
    }

    //执行更新类操作的事务，需要调用commit()才会提交
    public function execute($sql, array $params=array())
    {
        if (! $this->conn->inTransaction()) { //开启事务
            $this->conn->beginTransaction();
        }
        //自定义参数脱敏
        $sql = str_replace('?', '%s', $sql);
        foreach ($params as $i => $param) {
            $params[$i] = $this->quote($param);
        }
        $sql = vsprintf($sql, $params);
        $this->conn->exec($sql);
        return $this;
    }

    public function commit()
    {
        try {
            //lastInsertId()要在commit()之前获取，否则返回0
            $this->lastID = intval($this->conn->lastInsertId());
            $this->conn->commit();
        }
        catch(PDOException $e) {
            $this->conn->rollBack();
            return false;
        }
        return true;
    }

    public function lastInsertID()
    {
        return $this->lastID;
    }

    //查询类操作，保存并返回当前PDOStatement
    public function query($sql, array $params=array())
    {
        if ($this->stmt) { //关闭上一次的游标
            $this->stmt->closeCursor();
        }
        //echo $sql . "; <br />\n";
        $this->stmt = $this->conn->prepare($sql);
        $this->stmt->execute($params);
        return $this->stmt;
    }

    //批量DELETE操作
    public function doDelete($table_name, $where="", array $params=array())
    {
        if (! empty($conds)) {
            $sql = "DELETE FROM `" . $table_name . "`" . " " . trim($where);
        }
        else {
            $sql = "TRUNCATE TABLE `" . $table_name . "`";
        }
        return $this->execute($sql, $params)->commit();
    }

    //批量UPDATE操作
    public function doUpdate($table_name, array $data, $where="", array $params=array())
    {
        $sql = "UPDATE `" . $table_name . "`";
        $sql .= " SET `" . implode("`=?, `", array_keys($data)) . "`=?";
        $sql .= " " . trim($where);
        $params = array_merge(array_values($data), $params);
        return $this->execute($sql, $params)->commit();
    }

    //批量INSERT操作
    public function doInsert($table_name, array $row)
    {
        $rows = func_get_args();
        assert(count($rows) >= 2);
        $table_name = array_shift($rows);

        foreach ($rows as $row) {
            if (! empty($row) && is_array($row)) {
                $sql = "INSERT INTO `" . $table_name . "`";
                $sql .= " (`" . implode("`, `", array_keys($row)) . "`)";
                $pholders = substr(str_repeat(", ?", count($row)), 2);
                $sql .= " VALUES (" . $pholders . ")";
                $params = array_values($row);
                $this->execute($sql, $params);
            }
        }
        return $this->commit();
    }

    //批量REPLACE操作
    public function doReplace($table_name, array $rows=array())
    {
        if (empty($rows)) {
            return true;
        }
    }

    //SELECT操作，根据最后两个参数分解结果
    public function doSelect($table_name, $where="", array $params=array(),
                                $columns='*', $fetch='fetchAll')
    {
        $sql = "SELECT " . $columns . " FROM `" . $table_name . "`";
        $sql .= " " . trim($where);
        $this->query($sql, $params);
        $result = null;
        if (is_int($fetch)) {
            //例如 PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE 以第一个字段为键返回结果
            $result = $this->stmt->fetchAll($fetch);
        }
        else if (is_array($fetch)) {
            $method = array_shift($fetch);
            $result = call_user_func_array(array($this->stmt, $method), $fetch);
        }
        else if (method_exists($this->stmt, $fetch)) {
            $result = $this->stmt->$fetch();
        }
        //如果下次要调用nextRowset()，这里就不能关闭游标
        //$this->stmt->closeCursor();
        return $result;
    }
}


/**
 * 数据集合
 *
 * 使用：
 *     $coll->getColumn('COUNT(*)');
 *     $coll->load(array('is_active'=>1, 'id'=>array(4,5,6)));
 *     $coll->add($obj);
 *     $coll->sync();
 */
class Collection
{
    public $db;
    public $table_name = '';
    public $model_class = '';
    protected $fields = array(); //字段和默认值
    protected $objects = array(); //ID对应对象
    protected $phrases = array(); //限制条件

    public function __construct($db, $table_name='', $model_class='')
    {
        $this->db = $db;
        $this->table_name = $table_name;
        $this->model_class = $model_class;
    }

    //获取当前真实数据表名，包含前缀
    public function getTableName()
    {
        return $this->db->table_prefix . $this->table_name;
    }

    //获取数据表的字段名数组
    public function getFields()
    {
        if (count($this->fields) === 0) {
            $sql = "SHOW FULL COLUMNS FROM `" . $this->getTableName() . "`";
            $stmt = $this->db->query($sql);
            while($field = $stmt->fetchObject()) {
                $default = $field->Default;
                $this->fields[$field->Field] = $default;
            }
            $stmt->closeCursor();
        }
        return $this->fields;
    }

    //解析条件部分
    public function parsePhrases($quote=true)
    {
        $ops = array();
        $params = array();
        $holder = $quote ? "'%s'" : "?";
        foreach ($this->phrases as $key => $value) {
            //预处理key和数组value
            $key = trim($key);
            if (is_array($value)) {
                $count = count($value);
                if ($count == 0) {
                    $value = null;
                }
                else if ($count == 1) {
                    $value = $value[0];
                }
                else {
                    $holder_list = substr(str_repeat(", " . $holder, $count), 2);
                    array_push($ops, $key . " IN (" . $holder_list . ")");
                    if ($quote) {
                        foreach ($value as $val) { //全部脱敏处理
                            array_push($params, $this->db->quote($val));
                        }
                    }
                    else {
                        $params = array_merge($params, $value);
                    }
                    continue;
                }
            }
            //处理条件
            if (is_null($value)) {
                array_push($ops, $key . " IS NULL");
            }
            else if ($value instanceof SQLiteral) { //字面量，直接使用
                array_push($ops, $key . "=" . $value->value);
            }
            else {
                $value = $quote ? $this->db->quote($value) : $value;
                array_push($ops, $key . "=" . $holder);
                array_push($params, $value);
            }
        }
        $clause = empty($ops) ? "" : " WHERE (" . implode(" AND ", $ops) . ")";
        return array($clause, $params);
    }

    public function getColumn($columns, $extra='')
    {
        list($clause, $params) = $this->parsePhrases(false);
        $where = $clause . " " . trim($extra);
        return $this->db->doSelect($this->getTableName(), $where, $params, $columns, 'fetchColumn');
    }

    public function add($obj)
    {
        //类型检查1
        if (is_null($obj)) {
            return false;
        }
        //类型检查2
        $pkey_field = '';
        if (empty($this->model_class)) {
            $pkey_field = 'id';
        }
        else if ($obj instanceof $this->model_class) {
            $model_class = $this->model_class;
            $pkey_field = $model_class::getPKeyField();
        }
        //添加对象
        if (! empty($pkey_field)) { //满足条件
            $this->objects[$obj->id] = $obj;
            array_push($this->phrases[$pkey_field], $obj->id); //更新查询条件
            return true;
        }
    }

    public function &get($id)
    {
        if (empty($this->objects)) {
            $model_class = $this->model_class;
            $pkey_field = $model_class::getPKeyField();
            $this->load(array($pkey_field => $id));
        }
        if (array_key_exists($id, $this->objects)) {
            $obj = $this->objects[$id];
            return $obj;
        }
    }

    public function load(array $phrases=null, $extra='')
    {
        if (! empty($phrases)) {
            $this->phrases = array_merge($this->phrases, $phrases);
        }
        list($clause, $params) = $this->parsePhrases(false);
        $where = $clause . " " . trim($extra);
        $table_name = $this->getTableName();
        if (empty($this->model_class)) {
            $fetch = PDO::FETCH_OBJ | PDO::FETCH_UNIQUE;
            $pkey_field = 'id';
        }
        else {
            $fetch = array('fetchAll', PDO::FETCH_CLASS | PDO::FETCH_UNIQUE, $this->model_class);
            $model_class = $this->model_class;
            $model_class::$fields = $this->getFields();
            $pkey_field = $model_class::getPKeyField();
        }
        $columns = "$table_name.`$pkey_field`, $table_name.*";
        $this->objects = $this->db->doSelect($table_name, $where, $params, $columns, $fetch);
        $this->phrases = array($pkey_field => array_keys($this->objects)); //简化查询条件
        return $this->objects;
    }

    public function sync()
    {
        $rows = array();
        foreach ($this->objects as $obj) {
            if ($obj->isDirty()) {
                $obj->beforeSave();
                array_push($rows, $obj->toArray());
            }
        }
        $this->db->doReplace($this->getTableName(), $rows);
        #$this->load();
        /*echo "<br />\n<br />\n";
        var_dump($this->objects);
        echo "<br />\n<br />\n";*/
        return true;
    }
}


/**
 * 数据模型
 */
class Model
{
    const PKEY_FIELD = 'id';
    public static $fields = array();
    protected $changes = array(); //改动数据、脏数据
    public $id = 0;

    public function __construct()
    {
        $args = func_get_args();
        $pkey_field = static::getPKeyField();
        foreach ($args as $i => $arg) {
            $field = static::$fields[$i];
            if ($field === $pkey_field) {
                $this->id = $arg;
            }
            else {
                $this->$field = $arg;
            }
        }
    }

    public static function getPKeyField()
    {
        return static::PKEY_FIELD;
        return strtolower(static::PKEY_FIELD);
        //$curr_class = get_called_class();
        //return $curr_class::PKEY_FIELD;
    }

    public function isDirty()
    {
        return ! empty($this->changes);
    }

    public function get($field)
    {
        $pkey_field = static::getPKeyField();
        if ($field === $pkey_field) {
            return $this->id;
        }
        else if (array_key_exists($field, $this->changes)) {
            return $this->changes[$field]; //先在修改部分查找
        }
        else {
            return $this->$field;
        }
    }

    public function set($field, $value)
    {
        $this->changes[$field] = $value;
        return $this;
    }

    public function getChanges()
    {
        return $this->changes;
    }

    public function __get($field)
    {
        $method = 'get' . str_replace('_', '', $field);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        else {
            return $this->get($field);
        }
    }

    public function __set($field, $value)
    {
        $method = 'set' . str_replace('_', '', $field);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }
        else {
            return $this->set($field, $value);
        }
    }

    //将对象转化为数组格式
    public function toArray()
    {
        $pkey_field = static::getPKeyField();
        $data = get_object_vars($this);
        $data = array_merge($data, $this->changes);
        unset($data['changes']);
        return $data;
    }

    //保存前操作
    public function beforeSave()
    {
    }

    //保存后操作
    public function afterSave()
    {
        $pkey_field = static::getPKeyField();
        foreach ($this->changes as $field => $value) {
            if ($field === $pkey_field) {
                $this->id = $value;
            }
            else {
                $this->$field = $value;
            }
        }
        $this->changes = array();
    }
}
