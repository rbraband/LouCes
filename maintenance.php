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
#include_once('mysql.php');

/* del cache */
$cache_keys = $redis->getKeys('cache:*');
foreach($cache_keys as $cache) {
  $redis->del($cache);
}
echo count($cache_keys)." Cache Keys gelöscht!\n";
$str_time = (string)time();
/* maintenace */
// check users with none points
$users = $redis->hGetALL('users');
#$users = $redis->getKeys('user:[0-9]???:data');
$ll = 0;
$low = 0;
if (is_array($users)) foreach($users as $user) {
  #echo $user . "\n";
  $points = $redis->hGet("user:{$user}:data", 'points');
  $alliance_id = $redis->hGet("user:{$user}:data",'alliance_id');
  $count++;
  if ($points == 0) {
    $name = $redis->hGet("user:{$user}:data", 'name');
    echo "DELETE: {$user} | {$name}\n";
    $ll++;
    // delete from users
    $redis->hDel("users", $name);

    // fetch and delete aliase
    $aliase = $redis->sMembers("user:{$user}:alias");
    if (is_array($aliase)) foreach($aliase as $_alias) {

      $redis->hDel("aliase", $_alias);
    }
    // delete from continents
    $continent_keys = $redis->getKeys("user:{$user}:continent:*:data");
    if (is_array($continent_keys)) foreach($continent_keys as $_continent_key) {
      if (preg_match("/user:{$user}:continent:([0-9]*):data/sim", $_continent_key, $_matches)) {
        $redis->hDel("continent:{$_matches[1]}:residents", $name);
      }
    }
    // delete user from db
    $user_keys = $redis->getKeys("user:{$user}:*");
    if (is_array($user_keys)) foreach($user_keys as $_user_key) {
      $redis->del($_user_key);
    } 
     
    continue;
  }
  elseif ($points <=3) $low++;
  $user_cities = $redis->sMembers("user:{$user}:cities");
  if (is_array($user_cities)) foreach($user_cities as $city) {
    $city_id = $redis->hGet("cities", $city);
    $city_data = $redis->hGetALL("city:{$city_id}:data");
    if ($city_data['user_id'] != $user) {
      echo "DELETE User {$user} | city {$city}\n";
      $redis->sRem("user:{$user}:cities", $city);
      $redis->sRem("user:{$user}:continent:{$city_data['continent']}:cities", $city);
      if ($city_data['user_id'] >= 1) {
        $redis->sAdd("user:{$city_data['user_id']}:cities", $city);
        $redis->sAdd("user:{$city_data['user_id']}:continent:{$city_data['continent']}:cities", $city);
      }
      $city_key = "city:{$city_id}";
      $redis->hMset("{$city_key}:data", array(
        'll_time'        => $str_time,
        'll_name'        => $city_data['name'],
        'll_state'       => $city_data['state'],
        'll_points'      => $city_data['points'],
        'll_category'    => $city_data['category'],
        'll_user_id'     => $user,
        'll_alliance_id' => $alliance_id
      ));
    }
  }
} echo $count. " User, " . $ll . " LL und " . $low . " <= 3 Punkte\n";

$continents = $redis->sMembers("continents");
$settler_key = "settler";
if (is_array($continents)) foreach ($continents as $continent) {
  $continent_key = "continent:{$continent}";
  $redis->del("{$settler_key}:{$continent_key}:lawless");
  echo "\nDELETE lawless on K{$continent}";
}
echo "\n";

/* found deprecated aliases */
$aliases = $redis->hGetAll("aliase");
if (is_array($aliases)) foreach ($aliases as $alias => $user) {
  if (!$name = $redis->hGet("user:{$user}:data", 'name')) {
    echo "Error: deprecated {$user}|{$alias}\n";
  } #else echo "Found: {$user}|{$name}\n";
}

/* found cities with empty points */
$cities = $redis->hGetALL('cities');
if (is_array($cities)) foreach($cities as $city) {
  $points = $redis->hGet("city:{$city}:data", 'points');
  if ($points == 0) echo "ERROR: city {$city} with empty points\n";
}

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

/* clear stats older than */
echo "Remove > 4 weeks entrys\n";
$zsets = array('stats', 'reports', 'inactive', 'new', 'left', 'lawless', 'overtake', 'castles', 'palace', 'rename', 'military');
foreach($zsets as $zset) {
  $count = 0;
  $keys = $redis->getKeys("*:{$zset}");

  // delete stats > 72h
  // $latest = mktime(date("H") -72, 0, 0, date("n"), date("j"), date("Y"));
  // delete stats > 1 month
  $latest = mktime(date("H"), 0, 0, date("n") -1, date("j"), date("Y"));
  // delete stats > 1 week
  // $latest = mktime(date("H"), 0, 0, date("n"), date("j") -7, date("Y"));
  if (is_array($keys)) foreach($keys as $key) {
    $count = $count + $redis->ZREMRANGEBYSCORE("{$key}", "-inf", "({$latest}");
  }
  echo "DELETE {$count} entrys from ".count($keys)." {$zset} keys\n";
}
?>