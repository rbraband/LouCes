<?php
global $bot;
$bot->add_category('lists', array(), PUBLICY);

$bot->add_lists_hook("UpdateIgnores",               // command key
                    "LouBot_self_ignore_update",    // callback function
function ($bot, $list) {
  global $redis;
  if (empty($list['id'])||$list['id'] != IGNORE||!$redis->status()) return;
  if (is_array($list)) {
    $_ignorel = array();
    $ignore_key = "ignore";
    $alliance_key = "alliance:{$bot->ally_id}";
    foreach($list['data'] as $item) {
      if (!($ignoreId = $redis->hGet("{$ignore_key}:{$alliance_key}", $item['player_name']))) {
        //new ignore
        $_ignorel[] = $item['player_name'];
        $bot->log('Ignore: addttl ' . $item['player_name']);
        $redis->hSet("{$ignore_key}:{$alliance_key}", $item['player_name'], $item['id']);
        $uid = $bot->get_user_id($item['player_name']);
        $punish = "{$ignore_key}:{$alliance_key}:{$uid}";
        $redis->SET($punish, $item['id'], IGNORE_PUNISHTTL);
      } else {
        // check if expired and remove
        $uid = $bot->get_user_id($item['player_name']);
        $punish = "{$ignore_key}:{$alliance_key}:{$uid}";
        if ($ignoreId != $item['id']) {
          $redis->hSet("{$ignore_key}:{$alliance_key}", $item['player_name'], $item['id']);
          $redis->SET($punish, $item['id']);
        }
        if ($redis->TTL($punish) === -1) {
          if($bot->lou->del_ignore($ignoreId)) {
            $redis->hDel("{$ignore_key}:{$alliance_key}", $item['player_name']);
            $bot->log('Ignore: expired ' . $item['player_name']);
          }
        } else $_ignorel[] = $item['player_name'];
      }
    }
    if (!empty($_ignorel)) $bot->log('Ignorelist: ' . implode(', ', $_ignorel));
    else $bot->debug('Ignorelist: empty'); 
  }
}, 'lists');

$bot->add_tick_event(Cron::HOURLY,                       // Cron key
                    "GetSelfIgnorList",                 // command key
                    "LouBot_self_ignorel_cron",          // callback function
function ($bot, $data) {
  $bot->lou->get_self_ignorel();
}, 'lists');

// todo: FRIENDINV   FRIENDL   SUBSTITUTION   MAIL
?>