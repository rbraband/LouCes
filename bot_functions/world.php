<?php
global $bot;
$bot->add_category('statistic', array(), PUBLICY);
// crons
$bot->add_cron_event(Cron::DAILY,                           // Cron key
                    "DeleteOldStats",                       // command key
                    "LouBot_delete_old_stats_cron",         // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $zsets = array('stats', 'inactive', 'new', 'left', 'lawless', 'overtake', 'castles', 'palace', 'rename', 'military');
  foreach($zsets as $zset) {
    $keys = $redis->getKeys("*:{$zset}");
    // delete stats < 72h
    // $latest = mktime(date("H") - 72, 0, 0, date("n"), date("j"), date("Y"));
    // delete stats < 1 month
    $latest = mktime(date("H"), 0, 0, date("n")-1, date("j"), date("Y"));
    if (is_array($keys)) foreach($keys as $key) {
      $redis->ZREMRANGEBYSCORE("{$key}", "-inf", "({$latest}");
    }
  }
}, 'statistic');

$bot->add_cron_event(Cron::HOURLY,                          // Cron key
                    "GetContinentUpdate",                   // command key
                    "LouBot_stats_continent_update_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->SADD("stats:ContinentUpdate", (string)time());
  $bot->lou->get_continents_stat();
}, 'statistic');

$bot->add_cron_event(Cron::HOURLY,                          // Cron key
                    "GetAllianceUpdate",                    // command key
                    "LouBot_stats_alliance_update_cron",    // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->SADD("stats:AllianceUpdate", (string)time());
  $bot->lou->get_alliance_stat();
}, 'statistic');

$bot->add_cron_event(Cron::HOURLY,                          // Cron key
                    "GetPlayerUpdate",                      // command key
                    "LouBot_stats_all_player_update_cron",  // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->SADD("stats:PlayerUpdate", (string)time());
  $users = $redis->hVals("users");
  $bot->lou->get_player_stat_multi($users);
}, 'statistic');


$bot->add_cron_event(Cron::HOURLY,                                // Cron key
                    "GetContinentUpdatePlayer",                   // command key
                    "LouBot_stats_continent_player_update_cron",  // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->SADD("stats:ContinentPlayerUpdate", (string)time());
  $continents = $redis->SMEMBERS("continents");
  if (is_array($continents)) foreach ($continents as $continent) {
    if ($continent >= 0) {
      $bot->log('Redis get continent update K'.$continent);
      $bot->lou->get_player_by_continent_stat($continent, STAT_POINTS); // stat points 
      $bot->lou->get_player_by_continent_stat($continent, STAT_OFFENCE); // stat offence 
      $bot->lou->get_player_by_continent_stat($continent, STAT_DEFENCE); // stat defence
      $bot->lou->get_alliance_by_continent_stat($continent);
      $bot->lou->check();
    }
  }
}, 'statistic');

$bot->add_tick_event(Cron::TICK5,                          // Cron key
                    "GetLawlessUpdate",                    // command key
                    "LouBot_stats_lawless_update_cron",    // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->SADD("stats:LawlessUpdate", (string)time());
  $continents = $redis->SMEMBERS("continents");
  if (is_array($continents)) foreach ($continents as $continent) {
    $_lawless = array();
    $continent_key = "continent:{$continent}";
    $lawless = $redis->SMEMBERS("{$continent_key}:lawless");
    if (is_array($lawless) && !empty($lawless)) {
      foreach($lawless as $pos) {
        if ($_city = $redis->HGET('cities', $pos)) $_lawless[] = $_city;
      }
      if (is_array($_lawless) && !empty($_lawless)) $bot->lou->get_city_stat_multi_range($_lawless, $continent, $lawless);
    }
  } 
}, 'statistic');

$bot->add_tick_event(Cron::HOURLY,                         // Cron key
                    "GetWorldInactiveUpdate",              // command key
                    "LouBot_get_inactive_user_cron",       // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->SADD("stats:InactiveUpdate", (string)time());
  $users = $redis->hVals("users");
  $digest_key = "digest:users";
  $inactive = array();
  $str_time = (string)time();
  if(is_array($users)) foreach($users as $user) {
    // each user update
    $user_key = "user:{$user}";
    $user_info = $redis->HGETALL("{$user_key}:data");
    if (is_array($user_info) && $user_info['points'] == 0 && !empty($user_info['points'])) {
      $bot->log('Redis remove user '.REDIS_NAMESPACE.$user_key.' ('.$user_info['name'].') with '.$user_info['points'].' points!');
      $redis->HDEL("users", $user_info['name']);
      $inactive[] = $user;
    } else if(is_array($user_info) && empty($user_info['points'])) {
      $bot->log('Redis suspect user '.REDIS_NAMESPACE.$user_key.' ('.$user_info['name'].') with empty points!');
    }
  }
  if (!empty($inactive)) $redis->ZADD("{$digest_key}:inactive", $str_time, json_encode($inactive));
}, 'statistic');

// callbacks
$bot->add_statistic_hook("ContinentUpdate",                // command key
                         "LouBot_continent_update",        // callback function
function ($bot, $stat) {
  global $redis;
  $digest_key = "digest:continents";
  $continents = array();
  $str_time = (string)time();
  if (empty($stat['id'])||$stat['id'] != CONTINENT||!$redis->status()) return;
  if (is_array($stat['data']['continents'])) foreach ($stat['data']['continents'] as $continent) {
    if ($continent >= 0) {
      $update = $redis->SADD("continents", $continent);
      if ($update) {
        $bot->log('Redis add new continent K'.$continent);
        // new continent event here
        $continents[] = $continent;
        $bot->add_allymsg("Ein neuer Kontinent wurde entdeckt: K{$continent}");
      }
    }
  }
  if(!empty($continents)) $redis->ZADD("{$digest_key}:new", $str_time, json_encode($continents));
}, 'statistic');

$bot->add_statistic_hook("ContinentAllianceUpdate",                // command key
                         "LouBot_continent_alliance_update",       // callback function
function ($bot, $stat) {
  global $redis;
  if (empty($stat['id'])||$stat['id'] != ALLIANCE.RANGE||!$redis->status()) return;
  $continent = $stat['continent'];
  $str_time = (string)time();
  #print_r($stat);return;
  if (is_array($stat['data'])) {
    foreach ($stat['data'] as $item) {
      $alliance_key = "alliance:{$item['id']}";
      $redis->HMSET("{$alliance_key}:data", array(
        'name'      => $item['name'],
        'id'        => $item['id']
      ));
      // generate stat key: rank|points|members|cities|average
      $stats = sprintf('%d|%d|%d|%d|%d',$item['rank'], $item['points'], $item['members'], $item['cities'], $item['average']);
      if ($continent != '-1') {
        $continent_key = "continent:{$continent}";
        $bot->log('Redis update: '.REDIS_NAMESPACE.'alliance:'.$item['id'].' on continent: '.$continent);
        $redis->HMSET("{$alliance_key}:{$continent_key}:data", array(
          'rank'      => $item['rank'],
          'points'    => $item['points'],
          'members'   => $item['members'],
          'cities'    => $item['cities'],
          'average'   => $item['average']
        ));
        $redis->ZADD("{$alliance_key}:{$continent_key}:stats", $str_time, $stats);
      } else {
        $bot->log('Redis update: '.REDIS_NAMESPACE.'alliance:'.$item['id']);
        $redis->ZADD("{$alliance_key}:stats", $str_time, $stats);
        $redis->HMSET("alliances", array(
          $item['name'] => $item['id']
        ));
      }
    }
  }
}, 'statistic');  
    

$bot->add_statistic_hook("ContinentPlayerUpdate",                // command key
                         "LouBot_continent_player_update",       // callback function
function ($bot, $stat) {
  global $redis;
  if (empty($stat['id'])||$stat['id'] != PLAYER.RANGE||$stat['range'] != STAT_POINTS||!$redis->status()) return;
  $str_time = (string)time();
  $continent = $stat['continent'];
  #print_r($stat);return;
  if (is_array($stat['data'])) {
    $continent_key = "continent:{$continent}";
    $digest_key = "digest:{$continent_key}";
    $redis->RENAME("{$continent_key}:residents","{$continent_key}:_residents");
    $redis->RENAME("{$continent_key}:aliances","{$continent_key}:_aliances");
    $redis->RENAME("{$continent_key}:cities","{$continent_key}:_cities");
    $bot->log('Redis update: '.REDIS_NAMESPACE.'continent:'.$continent);
    $settler_keys = $redis->getKeys("settler:alliance:*:{$continent_key}:settlers:*");  
    foreach ($stat['data'] as $item) {
      if ($item['type'] == PLAYER && $item['id'] > 0) {
        // add player 2 continent
        $redis->SADD("{$continent_key}:residents", $item['name']);
        // add alliance 2 continent
        if (!empty($item['alliance'])) {
          $redis->SADD("{$continent_key}:aliances", $item['alliance']);
        }
        // each user update
        $user_key = "user:{$item['id']}";
        $redis->HMSET("users", array(
          $item['name'] => $item['id']
        ));
        $redis->HMSET("{$user_key}:data", array(
          'id'        => $item['id'],
          'name'      => $item['name'],
          'alliance'  => $item['alliance_id']
        ));
        $redis->SADD("{$user_key}:alias", mb_strtoupper($item['name']));
        $redis->HMSET("aliase", array(
          mb_strtoupper($item['name']) => $item['id']
        ));
        $redis->RENAME("{$user_key}:{$continent_key}:cities","{$user_key}:{$continent_key}:_cities");
        $_city_to_continent = 0;
        if (is_array($item['cities'])) foreach($item['cities'] as $city) {
          if ($city['continent'] != $continent) continue;
          #if ($redis->HGET('cities', $city['pos']) != $city['id']) continue;
          $_city_to_continent++;
          $city_key = "city:{$city['id']}";
          $bot->debug('Redis update: '.REDIS_NAMESPACE.$city_key);
          $redis->SADD("{$user_key}:{$continent_key}:cities", $city['pos']);
          $redis->SADD("{$continent_key}:cities", $city['pos']);
          $redis->HMSET("cities", array(
            $city['pos'] => $city['id']
          ));
          $redis->SADD("{$user_key}:cities", $city['pos']);
          // generate stat key: name|state|water|alliance_id|user_id|points
          $cstats = sprintf('%s|%d|%d|%d|%d|%d', $city['name'], $city['state'], $city['water'], $city['alliance_id'], $item['id'], $city['points']); 
          if (!$zadd = $redis->ZADD("{$city_key}:stats", $str_time, $cstats)) {
            $bot->debug("Redis zadd error: {$city_key}@{$str_time}|{$cstats}");
          }
          $city_info = $redis->HGETALL("{$city_key}:data");
          if (is_array($city_info) && !empty($city_info)) {
            if ($item['id'] != $city_info['user_id'])  $change_owner["{$city['pos']}"] = array($item['id'], $city_info['user_id']);
            if ($city['state'] != $city_info['state']) $change_state["{$city['pos']}"] = array($city['state'], $city_info['water']);
            if ($city['name'] != $city_info['name'])   $change_name["{$city['pos']}"]  = array($city['name'], $city_info['name']);
          }
          $redis->HMSET("{$city_key}:data", array(
            'name'        => $city['name'],
            'id'          => $city['id'],
            'category'    => $city['category'],
            'state'       => $city['state'],
            'water'       => $city['water'],
            'alliance_id' => $city['alliance_id'],
            'user_id'     => $item['id'],
            'points'      => $city['points'],
            'pos'         => $city['pos'],
            'x-coord'     => $city['x-coord'],
            'y-coord'     => $city['y-coord'],
            'continent'   => $city['continent']
          ));
          // remove ll
          if ($redis->SREM("{$continent_key}:lawless", $city['pos'])) {
            $redis->HDEL("{$city_key}:data", 'll_time');
            $redis->HDEL("{$city_key}:data", 'll_state');
            $redis->HDEL("{$city_key}:data", 'll_category');
            $redis->HDEL("{$city_key}:data", 'll_points');
            $redis->HDEL("{$city_key}:data", 'll_alliance_id');
            $redis->HDEL("{$city_key}:data", 'll_user_id');
            $redis->HDEL("{$city_key}:data", 'll_name');
            #$redis->HDEL("{$city_key}:data", 'll_name', 'll_user_id', 'll_alliance_id', 'll_points', 'll_category', 'll_state', 'll_time');
          }
          // remove settler
          if (is_array($settler_keys)) foreach($settler_keys as $settler_key) {
            if (strpos($settler_key, $city['pos']) !== false) $redis->DEL($settler_key);
          }
        }
        $bot->log('Redis update user '.REDIS_NAMESPACE.$user_key.' with '.$_city_to_continent.' cities on K'.$continent);
        // generate stat key: alliance_id|city_count|points|rank
        $stats = sprintf('%d|%d|%d|%d', $item['alliance_id'], $_city_to_continent, $item['points'], $item['rank']);
        $redis->ZADD("{$user_key}:{$continent_key}:stats", $str_time, $stats);
        $redis->HMSET("{$user_key}:{$continent_key}:data", array(
          'alliance_id' => $item['alliance_id'],
          'rank'      => $item['rank'],
          'points'    => $item['points'],
          'cities'    => $_city_to_continent
        ));
        $diff_old = $redis->SDIFF("{$user_key}:{$continent_key}:_cities","{$user_key}:{$continent_key}:cities");
        if (is_array($diff_old) && !empty($diff_old)) foreach($diff_old as $old) {
          $bot->debug("Redis: try to delete city from user and continent K{$continent}: {$old}");
          $redis->SREM("{$user_key}:cities", $old);
          //possible delete message here!
        }
        $diff_new = $redis->SDIFF("{$user_key}:{$continent_key}:cities","{$user_key}:{$continent_key}:_cities");
        if (is_array($diff_new) && !empty($diff_new)) foreach($diff_new as $new) {
          $bot->debug("Redis: try to welcome city to user and continent K{$continent}: {$new}");
          //no new message!
        }
        $redis->DEL("{$user_key}:{$continent_key}:_cities");
      }
    }
    // generate stat key: alliance_count|city_count|user_count
    $stats = sprintf('%d|%d|%d', $redis->SCARD("{$continent_key}:aliances"), $redis->SCARD("{$continent_key}:cities"), $redis->SCARD("{$continent_key}:residents"));
    $redis->ZADD("{$continent_key}:stats", $str_time, $stats);
    $redis->HMSET("{$continent_key}:data", array(
      'alliance_count' => $redis->SCARD("{$continent_key}:aliances"),
      'city_count'     => $redis->SCARD("{$continent_key}:cities"),
      'user_count'     => $redis->SCARD("{$continent_key}:residents")
    ));
    
    // *** digest 
    // SDIFF new old = newest
    // SDIFF old new = missing
    
    // *** residents
    $diff_old_residents = $redis->SDIFF("{$continent_key}:_residents","{$continent_key}:residents");
    if (is_array($diff_old_residents) && !empty($diff_old_residents)) {
      $bot->debug("Redis: try to delete residents from continent K{$continent}: " . implode(', ', $diff_old_residents));
      foreach($diff_old_residents as $old_resident) {
        // possible notice about single gone player
      }
      $redis->ZADD("{$digest_key}:residents:left", $str_time, json_encode($diff_old_residents));
    }
    $diff_new_residents = $redis->SDIFF("{$continent_key}:residents","{$continent_key}:_residents");
    if (is_array($diff_new_residents) && !empty($diff_new_residents)) {
      $bot->debug("Redis: try to welcome residents to continent K{$continent}: " . implode(', ', $diff_new_residents));
      foreach($diff_new_residents as $new_resident) {
        // possible notice about single new player
      }
      $redis->ZADD("{$digest_key}:residents:new", $str_time, json_encode($diff_new_residents));
    }
    $redis->DEL("{$continent_key}:_residents");
        
    // *** aliances
    $diff_old_aliances = $redis->SDIFF("{$continent_key}:_aliances","{$continent_key}:aliances");
    if (is_array($diff_old_aliances) && !empty($diff_old_aliances)) {
       $bot->debug("Redis: try to delete aliances from continent K{$continent}: " . implode(', ', $diff_old_aliances));
      foreach($diff_old_aliances as $old_aliance) {
        // possible notice about single gone alliance
      }
      $redis->ZADD("{$digest_key}:aliances:left", $str_time, json_encode($diff_old_aliances));
    }
    $diff_new_aliances = $redis->SDIFF("{$continent_key}:aliances","{$continent_key}:_aliances");
    if (is_array($diff_new_aliances) && !empty($diff_new_aliances)) {
      $bot->debug("Redis: try to welcome aliances to continent K{$continent}: " . implode(', ', $diff_new_aliances));
      foreach($diff_new_aliances as $new_aliance) {
        // possible notice about single new alliance
      }
      $redis->ZADD("{$digest_key}:aliances:new", $str_time, json_encode($diff_new_aliances));
    }
    $redis->DEL("{$continent_key}:_aliances");
    
    // *** cities
    $diff_old_cities = $redis->SDIFF("{$continent_key}:_cities", "{$continent_key}:cities");
    if (is_array($diff_old_cities) && !empty($diff_old_cities)) {
      $bot->debug("Redis: try to delete citys from continent K{$continent}: " . implode(', ', $diff_old_cities));
      foreach($diff_old_cities as $old_city) {
        // possible notice about single lawless city
        $redis->SADD("{$continent_key}:lawless", $old_city);
        $city_id = $redis->HGET('cities', $old_city);
        $city_key = "city:{$city_id}";
        $city = $redis->HGETALL("{$city_key}:data");
        $redis->HMSET("{$city_key}:data", array(
          'll_time'        => $str_time,
          'll_name'        => $city['name'],
          'll_state'       => $city['state'],
          'll_points'      => $city['points'],
          'll_user_id'     => $city['user_id'],
          'll_category'    => $city['category'],
          'll_alliance_id' => $city['alliance_id']
        ));
      }
      $redis->ZADD("{$digest_key}:cities:lawless", $str_time, json_encode($diff_old_cities));
    }
    $diff_new_cities = $redis->SDIFF("{$continent_key}:cities","{$continent_key}:_cities");
    if (is_array($diff_new_cities) && !empty($diff_new_cities)) {
      $bot->debug("Redis: try to welcome citys to continent K{$continent}: " . implode(', ', $diff_new_cities));
      foreach($diff_new_cities as $new_city) {
        // possible notice about single new city
      }
      $redis->ZADD("{$digest_key}:cities:new", $str_time, json_encode($diff_new_cities));
    }
    $redis->DEL("{$continent_key}:_cities");
    if (is_array($change_owner) && !empty($change_owner)) {
      $redis->ZADD("{$digest_key}:cities:overtake", $str_time, json_encode($change_owner));
    }
    if (is_array($change_state) && !empty($change_state)) {
      $new_castles = array_filter($change_state, function ($_city) { return ($_city[0] == 1); } );
      if (is_array($new_castles) && !empty($new_castles)) {
        $redis->ZADD("{$digest_key}:cities:castles", $str_time, json_encode($new_castles));
      }
      $new_palace = array_filter($change_state, function ($_city) { return ($_city[0] == 2); } );
      if (is_array($new_palace) && !empty($new_palace)) {
        $redis->ZADD("{$digest_key}:cities:palace", $str_time, json_encode($new_palace));
      }
    }
    if (is_array($change_name) && !empty($change_name)) {
      $redis->ZADD("{$digest_key}:cities:rename", $str_time, json_encode($change_name));
    }
    $redis->SADD("{$continent_key}:digest", $str_time);
  }
}, 'statistic');

$bot->add_statistic_hook("ContinentMilitaryUpdate",              // command key
                         "LouBot_continent_military_update",     // callback function
                         
function ($bot, $stat) {
  global $redis;
  if (empty($stat['id'])||$stat['id'] != PLAYER.RANGE||($stat['range'] != STAT_OFFENCE && $stat['range'] != STAT_DEFENCE)||!$redis->status()) return;
  $str_time = (string)time();
  $continent = $stat['continent'];
  #print_r($stat);return;
  $alliances = array();
  if (is_array($stat['data'])) {
    $continent_key = "continent:{$continent}";
    $military_key = ($stat['range'] == STAT_OFFENCE) ? 'offence' : 'defence';
    $redis->DEL("{$continent_key}:{$military_key}");
    $bot->log('Redis update: '.REDIS_NAMESPACE.'continent:'.$continent.':military');    
    foreach ($stat['data'] as $item) {
      if ($item['alliance_id'] == 0 && $item['points'] == 0) continue;
      else if ($item['points'] > 0) $alliances[$item['alliance_id']]['count'] ++;
      else if (empty($alliances[$item['alliance_id']]['count'])) $alliances[$item['alliance_id']]['count'] = 0;
      $alliances[$item['alliance_id']]['points'] += $item['points'];
    }
    if (is_array($alliances)) foreach($alliances as $alliance_id => $military) {
      $alliance_key = "alliance:{$alliance_id}";
      // generate stat key: alliance_id|type|count|points
      $stats = sprintf('%d|%d|%d|%d', $alliance_id, $stat['range'], $military['count'], $military['points']);
      $redis->ZADD("{$continent_key}:military", $str_time, $stats);
      // generate stat key: type|count|points
      $stats = sprintf('%d|%d|%d', $stat['range'], $military['count'], $military['points']);
      $redis->ZADD("{$alliance_key}:{$continent_key}:military", $str_time, $stats);
      $redis->HMSET("{$continent_key}:{$military_key}", array(
        $alliance_id => $military['points'].'|'.$military['count']
      ));
      $redis->HDEL("{$alliance_key}:{$military_key}", $continent);
      $redis->HMSET("{$alliance_key}:{$military_key}", array(
        $continent => $military['points'].'|'.$military['count']
      ));
    }
  }
}, 'statistic');

$bot->add_statistic_hook("PlayerUpdate",                 // command key
                         "LouBot_player_update",         // callback function
function ($bot, $stat) {
  global $redis;
  if (empty($stat['id'])||$stat['id'] != PLAYER||!$redis->status()) return;
  $str_time = (string)time();
  #print_r($stat);return;
  if (is_array($stat['data']) && $stat['data']['id'] > 0) {
    $user_key = "user:{$stat['data']['id']}";
    $digest_key = "digest:{$user_key}";
    $bot->log('Redis update: '.REDIS_NAMESPACE.$user_key);
    $redis->HMSET("users", array(
      $stat['data']['name'] => $stat['data']['id']
    ));
    // *** digest
    #$unser_info = $redis->HGETALL("{$user_key}:data");
    #if (is_array($unser_info) && !empty($unser_info)) {
    #  if ($stat['data']['alliance_id'] != $unser_info['alliance'])  $redis->ZADD("{$digest_key}:alliance", $str_time, json_encode(array($stat['data']['alliance_id'], $unser_info['alliance'])));
    #}
    #$redis->SADD("{$user_key}:digest", $str_time);
    #$redis->DEL("{$user_key}:digest");
    $redis->HMSET("{$user_key}:data", array(
      'id'        => $stat['data']['id'],
      'name'      => $stat['data']['name'],
      'rank'      => $stat['data']['rank'],
      'points'    => $stat['data']['points'],
      'alliance'  => $stat['data']['alliance_id']
    ));
    // generate stat key: alliance_id|city_count|points|rank
    $stats = sprintf('%s|%d|%d|%d', $stat['data']['alliance_id'], count($stat['data']['cities']), $stat['data']['points'], $stat['data']['rank']);
    if (!$zadd = $redis->ZADD("{$user_key}:stats", $str_time, $stats)) {
      $bot->debug("Redis zadd error: {$user_key}@{$str_time}|{$stats}");
    }
  }
}, 'statistic');

$bot->add_statistic_hook("LawlessCityUpdate",                 // command key
                         "LouBot_lawless_city_update",        // callback function
function ($bot, $stat) {
  global $redis;
  if (empty($stat['id'])||$stat['id'] != CITY.RANGE||!$redis->status()) return;
  $continent = $stat['continent'];
  $continent_key = "continent:{$continent}";
  $cities = $stat['range'];
  $_cities = array();
  $str_time = (string)time();
  
  if (is_array($stat['data'])) {
    foreach ($stat['data'] as $item) {
      if ($item['player_id'] == 0 && $item['alliance_id'] == 0) $_cities[] = $item['pos'];
      #$_cities[] = $item['pos'];
      if ($city_id = $redis->HGET('cities', $item['pos'])) {
        $city_key = "city:{$city_id}";
        $bot->log('Redis update: '.REDIS_NAMESPACE.$city_key);
        // generate stat key: name|state|water|alliance_id|user_id|points
        $cstats = sprintf('%s|%d|%d|%d|%d|%d', $item['name'], $item['state'], $item['water'], $item['alliance_id'], $item['player_id'], $item['points']); 
        $redis->ZADD("{$city_key}:stats", $str_time, $cstats);
        $redis->HMSET("{$city_key}:data", array(
          'name'        => $item['name'],
          'state'       => $item['state'],
          'points'      => $item['points'],
          'category'    => $item['category'],
          'alliance_id' => $item['alliance_id'],
          'user_id'     => $item['player_id']
        ));
      }
    }
  }
  $to_be_deleted = array_diff($cities, $_cities);
  $settler_keys = $redis->getKeys("settler:alliance:*:{$continent_key}:settlers:*");
  if(is_array($to_be_deleted)) foreach($to_be_deleted as $item) {
    // remove LL
    $bot->log("Delete lawless: {$item}");
    $redis->SREM("{$continent_key}:lawless", $item);
    // remove settler
    #$settler_keys = $redis->getKeys("settler:alliance:*:{$continent_key}:settlers:{$item}");
    if (is_array($settler_keys)) foreach($settler_keys as $settler_key) {
      if (strpos($settler_key, $item) !== false) $redis->DEL($settler_key);
    }
  }
}, 'statistic');
?>