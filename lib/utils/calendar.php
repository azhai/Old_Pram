<?php
/**
 * Project Pram (http://git.oschina.net/azhai/pram)
 *
 * @copyright 2013 FreeBSD License
 * @author Ryan Liu <azhai@126.com>
 */

require __DIR__ . '/moonphase.php';
@date_default_timezone_set('Asia/Shanghai');


/**
 * 获得立春的日期
 */
function get_spring_day($year)
{   
    $fixes = array('20' => 4.6295, '21' => 3.87, '22' => 4.15); #修正量
    $year = intval($year);
    $century = strval(ceil($year / 100));
    $figures = $year % 100;
    $fix = array_key_exists($century, $fixes) ? $fixes[$century] : 4; 
    return floor($figures * 0.2422 + $fix) - floor(($figures - 1) / 4);
}


/**
 * 获得生肖index，以立春为分界线
 */
function get_birth_animal_index($year, $month_day)
{
    $year = intval($year);
    $month_day = intval($month_day);
    if ($month_day < 200 + get_spring_day($year)) { //立春，公历2月3、4、5、6日
        $year -= 1;
    }
    $index = ($year - 1900) % 12; //1900年是鼠年
    return $index;
}


/**
 * 获得星座index
 */
function get_horoscope_index($month_day)
{
    $horos = array( //星座分界线，公历
        120, 219, 321, 420, 521, 622, 723,
        823, 923, 1024, 1123, 1222,
    );
    $month_day = intval($month_day);
    $index = floor($month_day / 100) - 1;
    if ($month_day < $horos[$index]) {
        $index = $index == 0 ? 11 : $index - 1;
    }
    return $index;
}


/**
 * 从农历转为公历，leap闰月
 */
function from_lunar($dt, $leap=false)
{
    $dt = $dt instanceof DateTime ? $dt : new DateTime($dt);
    $moon = new MoonPhase($dt->getTimestamp() + 86400 * 30); //公历大约在农历一个月以后
    $offset = (intval($dt->format('d')) - 1) * 86400;
    $new_moon = $leap ? $moon->next_new_moon() : $moon->new_moon(); //找到农历初一对应的公历日期
    $solar = new DateTime();
    $solar->setTimestamp($new_moon + $offset);
    return array(
        intval($solar->format('Y')),
        intval($solar->format('m')),
        intval($solar->format('d')),
    );
}
