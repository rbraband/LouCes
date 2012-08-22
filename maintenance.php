#!/usr/bin/php
<?php
/*
PHPLoU_bot - an LoU bot writen in PHP
Copyright (C) 2012 Roland Braband / rbraband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

include_once('config.php');
include_once('redis.php');

/* del cache */
$cache_keys = $redis->getKeys('cache:*');
foreach($cache_keys as $cache) {
  $redis->del($cache);
}
echo count($cache_keys)." Cache Keys gelöscht!\n";

/* maintenace */
// check users with none points
$users = $redis->HGETALL('users');
#$users = $redis->getKeys('user:[0-9]???:data');
$ll = 0;
$low = 0;
if (is_array($users)) foreach($users as $user) {
  #echo $user . "\n";
  $points = $redis->hget("user:{$user}:data", 'points');
  $count++;
  if ($points == 0) {
    echo $redis->hget("user:{$user}:data", 'id').' | ' . $redis->hget("user:{$user}:data", 'name')."\n";
    $ll++;
    $redis->HDEL("users", $redis->hget("user:{$user}:data", 'name'));
    $redis->DEL("user:{$user}:cities");
    $konti_keys = $redis->getKeys("user:{$user}:continent:*");
    if (is_array($konti_keys)) foreach($konti_keys as $_konti_key) {
      $redis->DEL($_konti_key);
    }
    continue;
  }
  elseif ($points <=3) $low++;
  $user_cities = $redis->SMEMBERS("user:{$user}:cities");
  if(is_array($user_cities)) foreach($user_cities as $city) {
    $city_id = $redis->HGET("cities", $city);
    $city_data = $redis->HGETALL("city:{$city_id}:data");
    if ($city_data['user_id'] != $user) {
      echo "!Error!\n";
      $redis->SREM("user:{$user}:cities", $city);
      $redis->SREM("user:{$user}:continent:{$city_data['continent']}:cities", $city);
      $redis->SADD("user:{$city_data['user_id']}:cities", $city);
      $redis->SREM("user:{$city_data['user_id']}:continent:{$city_data['continent']}:cities", $city);
    }
  }
} echo $count. " User, " . $ll . " LL und " . $low . " <= 3 Punkte\n";

$continents = $redis->SMEMBERS("continents");
$settler_key = "settler";
if (is_array($continents)) foreach ($continents as $continent) {
  $continent_key = "continent:{$continent}";
  $redis->DEL("{$settler_key}:{$continent_key}:lawless");
  echo "\nDelete lawless on K{$continent}";
}
echo "\n";

/* clear stats
$date = mktime(date("H"), 0, 0, date("n")-1, date("j"), date("Y"));
$stats = $redis->getKeys('*:stats');
echo "\nDelete Stat Keys: < ".date('d.m.Y',$date)." on " . count($stats) . " keys";

foreach($stats as $k => $v) {
  $_stats = $redis->ZRANGEBYSCORE("{$v}", "-inf", "({$date}");
  $redis->ZREMRANGEBYSCORE("{$v}", "-inf", "({$date}");
  if (count($_stats) > 0) echo "\n Key {$v} have ". count($_stats) . " keys to be deletet!";
}
*/

/* del reports
echo "\n";
echo "Delete Reports\n";
$report_keys = $redis->getKeys("*reports*");
if (is_array($report_keys)) foreach($report_keys as $_report_key) {
  $redis->DEL($_report_key);
}
echo count($report_keys) . " Keys gelöscht!\n";
*/
/*
echo "Copy Stats\n";
$keys = $redis->getKeys('*:stats');
$latest = mktime(date("H") - 72, 0, 0, date("n"), date("j"), date("Y"));
if (is_array($keys)) { 
  foreach($keys as $key) {
    $stats = array_flip($redis->ZRANGEBYSCORE("{$key}", "-inf", "({$latest}", array('withscores' => TRUE)));
    if(!empty($stats)) foreach($stats as $time => $stat) {
      // fill in MySql $key, $time, $stat
      $query = 'INSERT INTO `lou:stats` (`key`,`time`,`value`) VALUES (\'%s\',\'%s\',\'%s\')';
      $mysql->Queryf($query, $key, date('Y-m-d H:i:s', $time), $stat);
    }
  }
}
*/

/* clear stats > 4 weeks */
echo "Remove > 4 week entrys\n";
$zsets = array('stats', 'reports', 'inactive', 'new', 'left', 'lawless', 'overtake', 'castles', 'palace', 'rename', 'military');
foreach($zsets as $zset) {
  $keys = $redis->getKeys("*:{$zset}");
  echo "delete from ".count($keys)." {$zset}\n";
  // delete stats < 72h
  // $latest = mktime(date("H") - 72, 0, 0, date("n"), date("j"), date("Y"));
  // delete stats < 1 month
  $latest = mktime(date("H"), 0, 0, date("n")-1, date("j"), date("Y"));
  if (is_array($keys)) foreach($keys as $key) {
    $redis->ZREMRANGEBYSCORE("{$key}", "-inf", "({$latest}");
  }
}
?>