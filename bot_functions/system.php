<?php
global $bot;
$bot->add_category('system', array(), PRIVACY);
// crons

// callbacks
$bot->add_system_hook("ServerUpdate",             // command key
                      "LouBot_server_update",     // callback function
function ($bot, $data) {
  global $redis;
  if (empty($data['id'])||$data['id'] != SERVER||!$redis->status()) return;
  $server_key = "server";
  $str_time = (string)time();
  if ($redis->get("server:url") != $data['url']) $bot->log('Server url changed to: ' . $data['url']);
  $redis->set("server:url", $data['url']);
  if (($world = $redis->get("server:world")) && ($world != $data['name'])) {
      $bot->log('Server world changed to: ' . $data['name']);
      $bot->log('Exit now!');
      exit(-1);
  }
  $redis->set("server:world", $data['name']);
  if ($redis->get("server:version") != $data['version']) $bot->log('Server version changed to: ' . $data['version']);
  $redis->set("server:version", $data['version']);
  $redis->set("server:width", $data['width']);
  $redis->set("server:height", $data['height']);
  $redis->set("server:chars", $data['chars']);
}, 'system');
?>