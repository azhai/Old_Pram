<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

defined('DATA_DIR') or define('DATA_DIR', dirname(dirname(__FILE__)) . '/datas');


/**
 * 二分（折半）查找算法
 */
function binary_search($total, $callback)
{
    $args = func_get_args();
    array_shift($args);
    $step = $total;
    $offset = 0;
    $sign = 1;
    do {
        $step = ceil($step / 2);
        $offset += $sign * $step;
        array_splice($args, 0, 1, $offset);
        $sign = call_user_func_array($callback, $args);
    } while ($sign !== 0 && $step > 1);
    return $sign === 0;
}


function compare_ip($offset, $ip, $fp)
{
    fseek($fp, ($offset - 1) * 8);
    $start_ip = fread($fp, 4); //读取之后0-4个字节
    $end_ip = fread($fp, 4); //读取之后4-8个字节
    $sign = strcmp($ip, bin2hex($start_ip));
    if ($sign >= 0 && strcmp($ip, bin2hex($end_ip)) <= 0) {
        return 0; //在IP段范围内（含两端）
    }
    else { //指出下次偏移的方向
        return $sign;
    }
}


/**
 * 是否中国大陆IP
 */
function is_china_ip($ip_address)
{
    //IP段数据文件，每个IP表示成一个8位hex整数，不足8位的前面补0
    $datfile = DATA_DIR . '/cnips.dat';
    $total = floor(filesize($datfile) / 8); //IP段总数
    //将要判断的IP转为8位hex整数
    $ip = sprintf('%08x', ip2long($ip_address));
    $fp = fopen($datfile, 'rb');
    //比较IP并决定方向
    $result = binary_search($total, 'compare_ip', $ip, $fp); //请在这以后关闭文件
    fclose($fp);
    return $result;
}


/**
 * IP头黑名单/白名单
 */
function ip_in_list($ip_address) 
{
    try {
        $iplist = (include DATA_DIR . '/iplist.php');
    }
    catch (Exception $e) {
        $iplist = array('black_list'=>array(), 'white_list'=>array());
    }
    $black_ip_list = isset($iplist['black_list']) ? $iplist['black_list'] : array();
    $white_ip_list = isset($iplist['white_list']) ? $iplist['white_list'] : array();
    
    $pics = explode('.', trim($ip_address));
    if (count($pics) == 4) {
        $ip_head = $pics[0] . '.' . $pics[1] . '.';
        if (in_array($ip_head, $white_ip_list, true)) {
            return 1; //在白名单中
        }
        else if (in_array($ip_head, $black_ip_list, true)) {
            return -1; //在黑名单中
        }
    }
    return 0; //待定
}


/**
 * 禁止大陆用户使用境外黑信用卡充值
 */
function is_block_ip($ip_address, $money=0, $money_xx=0) 
{
    try {
        $foreign_currency = intval($money) !== intval($money_xx); //境外货币
    }
    catch (Exception $e) {
        $foreign_currency = true;
    }
    if ($foreign_currency === false) { //人民币，不用判断IP了
        return false;
    }
    
    try {
        //禁止的IP
        $black_ip = ip_in_list($ip_address);
        if ($black_ip === 0) {
            $black_ip = is_china_ip($ip_address);
        }
    }
    catch (Exception $e) {
        $black_ip = false;
    }
    return $foreign_currency && ($black_ip === true || $black_ip === -1);
}
