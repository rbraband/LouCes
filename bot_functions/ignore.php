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
    foreach($list['data'] as $item) {
      $ignore_key = "ignore";
      $alliance_key = "alliance:{$bot->ally_id}";
      $_ignorel[] = $item['player_name'];
      if (!($ignoreId = $redis->hGet("{$ignore_key}:{$alliance_key}", $item['player_name']))) {
        //new ignore
        $redis->hSet("{$ignore_key}:{$alliance_key}", $item['player_name'], $item['id']);
      } else {
        // todo: check if expired and remove
        if ($ignoreId != $item['id']) $redis->hSet("{$ignore_key}:{$alliance_key}", $item['player_name'], $item['id']);
      }
    } 
    $bot->log('Ignorelist: ' . implode(', ', $_ignorel)); 
  }
}, 'lists');

$bot->add_tick_event(Cron::HOURLY,							 				// Cron key
										"GetSelfIgnorList",                 // command key
										"LouBot_self_ignorel_cron",    		  // callback function
function ($bot, $data) {
  $bot->lou->get_self_ignorel();
}, 'lists');

// todo: FRIENDINV   FRIENDL   SUBSTITUTION   MAIL
?>