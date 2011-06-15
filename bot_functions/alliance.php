<?php
global $bot;
$bot->add_category('alliance', array(), PUBLICY);

$bot->add_alliance_hook("Update",                        // command key
                        "LouBot_alliance_update",        // callback function
function ($bot, $data) {
  global $redis;
  if (empty($data['id'])||!$redis->status()) return;
  $alliance_key = "alliance:{$data['id']}";
  $bot->log('Redis update: '.REDIS_NAMESPACE.$alliance_key);
  $redis->HMSET("alliances", array(
    $data['name'] => $data['id']
  ));
  $redis->HMSET("{$alliance_key}:data", array(
    'name'      => $data['name'],
    'id'        => $data['id'],
    'short'     => $data['short'],
    'announce'  => $data['announce'],
    'desc'      => $data['desc']
    ));
  $redis->RENAME("{$alliance_key}:member","{$alliance_key}:_member");
  if (is_array($data['member'])) foreach($data['member'] as $member) {
    $redis->SADD("{$alliance_key}:member", $member['name']);
    $redis->HMSET("users", array(
      $member['name'] => $member['id']
    ));
    $user_key = "user:{$member['id']}";
    $redis->HMSET("{$user_key}:data", array(
      'id'        => $member['id'],
      'name'      => $member['name'],
      'role'      => $member['role'],
      'rank'      => $member['rank'],
      'points'    => $member['points'],
      'state'     => $member['state'],
      'lastlogin' => $member['lastlogin'],
      'title'     => $member['title'],
      'alliance'  => $data['id']
    ));
    $redis->SADD("{$user_key}:alias", mb_strtoupper($member['name']));
    $redis->HMSET("aliase", array(
      mb_strtoupper($member['name']) => $member['id']
    ));
  }
  $diff = $redis->SDIFF("{$alliance_key}:member","{$alliance_key}:_member");
  if (is_array($diff)) foreach($diff as $old) {
    $bot->log("Redis: try to delete user: ", print_r($old));
    $uid = $redis->HGET('users', $old);
    $redis->DEL("user:{$uid}:data");
    $aliase = $redis->SMEMBERS("user:{$uid}:alias");
    if (is_array($aliase)) foreach($aliase as $alias) {
      $redis->HDEL('aliase', $alias);
    }
    $redis->DEL("user:{$uid}:alias");
    $redis->HDEL('users', $old);
  }
  $redis->DEL("{$alliance_key}:_member");
  if (is_array($data['roles'])) foreach($data['roles'] as $key => $role) {
    $role_key = "{$alliance_key}:roles";
    $redis->HMSET($role_key, array(
      $key => $role
    ));
  }
  if (is_array($data['diplomacy'])) foreach($data['diplomacy'] as $key => $relation) {
    $relation_key = "{$alliance_key}:diplomacy";
    $redis->HMSET("{$relation_key}", array(
      $relation['name'] => $relation['state']
    ));
  }
}, 'alliance');

$bot->add_alliance_hook("SetAllyShort",                        // command key
                        "LouBot_alliance_update_shortname",    // callback function
function ($bot, $data) {
  if (empty($data['id'])||$data['id'] != $bot->ally_id||$bot->ally_shortname == $data['short']) return;
  $bot->set_ally_shortname($data['short']);
  $bot->log("Set AllianceShort: " . $bot->ally_shortname);
}, 'alliance');

$bot->add_tick_event(Cron::TICK5,							 						// Cron key
										"GetAllyUpdate",                      // command key
										"LouBot_alliance_update_cron",    		// callback function
function ($bot, $data) {
  $bot->lou->get_alliance();
}, 'alliance');
?>