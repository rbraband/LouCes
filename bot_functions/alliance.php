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
  $diff_old = $redis->SDIFF("{$alliance_key}:_member","{$alliance_key}:member");
  if (is_array($diff_old)) foreach($diff_old as $old) {
    $bot->log("Redis: try to delete user from alliance and bot: {$old}");
    $uid = $redis->HGET('users', $old);
    // needs an extra event for delete user from alliance and bot?
    // aliase
    /*$aliase = $redis->SMEMBERS("user:{$uid}:alias");
    if (is_array($aliase)) foreach($aliase as $alias) {
      $redis->HDEL('aliase', $alias);
    }
    $redis->DEL("user:{$uid}:alias");*/
    // bookmarks
    $bookmarks = $redis->SMEMBERS("user:{$uid}:bookmarks");
    if (is_array($bookmarks)) foreach($bookmarks as $bookmark) {
      $redis->HDEL('bookmarks', $bookmark);
    }
    $redis->DEL("user:{$uid}:bookmarks");
  }
  $diff_new = $redis->SDIFF("{$alliance_key}:member","{$alliance_key}:_member");
  if (is_array($diff_new)) foreach($diff_new as $new) {
    $bot->log("Redis: try to welcome user to alliance and bot: {$new}");
    $uid = $redis->HGET('users', $new);
    // needs an extra event for welcome user to alliance and bot
    #$bot->add_allymsg("Willkommen bei {$bot->ally_name} {$new}!");
  }
  $redis->DEL("{$alliance_key}:_member");
  if (is_array($data['roles'])) foreach($data['roles'] as $key => $role) {
    $role_key = "{$alliance_key}:roles";
    $redis->HMSET($role_key, array(
      $key => $role
    ));
  }
  if (is_array($data['diplomacy'])) {
    $relation_key = "{$alliance_key}:diplomacy";
    $redis->DEL("{$relation_key}");
    foreach($data['diplomacy'] as $key => $relation) {
    $redis->HMSET("{$relation_key}", array(
      $relation['name'] => $relation['state']
    ));
  }
  }
}, 'alliance');

$bot->add_alliance_hook("SetAllyShort",                        // command key
                        "LouBot_alliance_update_shortname",    // callback function
function ($bot, $data) {
  if (empty($data['id'])||$data['id'] != $bot->ally_id||$bot->ally_shortname == $data['short']) return;
  $bot->set_ally_shortname($data['short']);
  $bot->log("Set AllianceShort: " . $bot->ally_shortname);
}, 'alliance');

$bot->add_tick_event(Cron::TICK5,                           // Cron key
                    "GetAllyUpdate",                      // command key
                    "LouBot_alliance_update_cron",        // callback function
function ($bot, $data) {
  $bot->lou->get_self_alliance();
}, 'alliance');
?>