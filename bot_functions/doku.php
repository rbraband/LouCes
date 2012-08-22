<?php
global $bot;
$bot->add_category('doku', array(), PUBLICY);
// crons
$bot->add_cron_event(Cron::HOURLY,                    // Cron key
                    "GetDokuUpdate",                  // command key
                    "LouBot_doku_update_cron",        // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  
  $alliance_key = "alliance:{$bot->ally_id}";
  $doku_key = "doku";
  $str_time = (string)time();
  $doku_name = "{$bot->bot_user_name}'s Welt";
  if (!($forum_id = $redis->GET("{$doku_key}:{$alliance_key}:forum:id"))) {
    $forum_id = $bot->forum->get_forum_id_by_name($doku_name, true);
    $redis->SET("{$doku_key}:{$alliance_key}:forum:id", $forum_id);
  }
  $last_update = $redis->SMEMBERS('stats:DokuUpdate');
  sort($last_update);
  $last_update = end($last_update);
  $dokus = load_doku();
  if (is_array($dokus) && $forum_id) foreach ($dokus as $doku) {
    $bot->lou->check(); if(empty($doku['title'])) continue;
    $thread_name = $doku['title'];
    $bot->log("Doku forum '{$thread_name}': start");
    if (!($thread_id = $redis->GET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:id"))) {
      $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
      $redis->SET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:id", $thread_id);
    }
    $force = false;
    if (!($_version = $redis->GET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:version"))) {
      $force = true;
    }
    $update = version_compare($doku['version'], $_version, '>');
    if ($thread_id && ($update || $force)) {
      // ** forum
      $bot->forum->get_alliance_forum_posts($forum_id, $thread_id);
      $redis->SET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:version", $doku['version']);
      $_post_id = 0;
      $post = array();
      foreach($doku['entrys'] as $entry) {
        $post[$_post_id] = $entry;
        $_post_id ++;
      }
      $post_update = "[u]letztes Update:[/u] [i]{$doku['date']}[/i] | [u]Dokumentation:[/u] [spieler]{$doku['author']}[/spieler]";
      foreach ($post as $_post_id_post => $_post) {
        if (is_array($bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id_post])) {
          if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id_post]['post_id'], $_post)) $bot->log("Doku forum '{$thread_name}'/{$thread_id}/{$_post_id_post}: edit post error!");
        } else {
          if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $_post)) $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
        }
      }
        
      if ($update && count($bot->forum->posts[$forum_id][$thread_id]['data']) >= count($post)) {
        $bot->log("Doku forum '{$thread_name}': update({$doku['date']}) v{$doku['version']} posts:" . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
        for($idx = count($post); $idx <= count($bot->forum->posts[$forum_id][$thread_id]['data']); $idx++) {
          $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$idx]['post_id']);
        }
        if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post_update)) $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
      } else {
        $post[$_post_id] = $post_update;
        $bot->log("Doku forum '{$thread_name}': update({$doku['date']}) v{$doku['version']} posts:" . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
        for($idx = count($post); $idx <= count($bot->forum->posts[$forum_id][$thread_id]['data']); $idx++) {
          $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$idx]['post_id']);
        }
        if (is_array($bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id])) {
          if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id]['post_id'], $post[$_post_id])) $bot->log("Doku forum '{$thread_name}'/{$thread_id}/{$_post_id}: edit post error!");
        } else {
          if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post[$_post_id])) $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
        }
      }
    }
  } else $bot->log("Doku error: no forum '{$doku_name}'");
}, 'doku');


//callbacks
$bot->add_privmsg_hook("ReloadDoku",           // command key
                       "LouBot_reload_doku",   // callback function
                       true,                   // is a command PRE needet?
                       '',                     // optional regex for key
function ($bot, $data) {
  global $redis;
  if($bot->is_op_user($data['user'])) {
    if (!$redis->status()) return;
  
    $alliance_key = "alliance:{$bot->ally_id}";
    $doku_key = "doku";
    $str_time = (string)time();
    $doku_name = "{$bot->bot_user_name}'s Welt";
    if (!($forum_id = $redis->GET("{$doku_key}:{$alliance_key}:forum:id"))) {
      $forum_id = $bot->forum->get_forum_id_by_name($doku_name, true);
      $redis->SET("{$doku_key}:{$alliance_key}:forum:id", $forum_id);
    }
    $last_update = $redis->SMEMBERS('stats:DokuUpdate');
    sort($last_update);
    $last_update = end($last_update);
    $dokus = load_doku();
    if (is_array($dokus) && $forum_id) foreach ($dokus as $doku) {
      $bot->lou->check(); if(empty($doku['title'])) continue;
      $thread_name = $doku['title'];
      $bot->log("Doku forum '{$thread_name}': start");
      if (!($thread_id = $redis->GET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:id"))) {
        $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
        $redis->SET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:id", $thread_id);
      }
      $force = false;
      if (!($_version = $redis->GET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:version"))) {
        $force = true;
      }
      $update = version_compare($doku['version'], $_version, '>');
      if ($thread_id && ($update || $force)) {
        // ** forum
        $bot->forum->get_alliance_forum_posts($forum_id, $thread_id);
        $redis->SET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:version", $doku['version']);
        $_post_id = 0;
        $post = array();
        foreach($doku['entrys'] as $entry) {
          $post[$_post_id] = $entry;
          $_post_id ++;
        }
        $post_update = "[u]letztes Update:[/u] [i]{$doku['date']}[/i] | [u]Dokumentation:[/u] [spieler]{$doku['author']}[/spieler]";
        foreach ($post as $_post_id_post => $_post) {
          if (is_array($bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id_post])) {
            if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id_post]['post_id'], $_post)) $bot->log("Doku forum '{$thread_name}'/{$thread_id}/{$_post_id_post}: edit post error!");
          } else {
            if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $_post)) $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
          }
        }
          
        if ($update && count($bot->forum->posts[$forum_id][$thread_id]['data']) >= count($post)) {
          $bot->log("Doku forum '{$thread_name}': update({$doku['date']}) v{$doku['version']} posts:" . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
          for($idx = count($post); $idx <= count($bot->forum->posts[$forum_id][$thread_id]['data']); $idx++) {
            $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$idx]['post_id']);
          }
          if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post_update)) $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
        } else {
          $post[$_post_id] = $post_update;
          $bot->log("Doku forum '{$thread_name}': update({$doku['date']}) v{$doku['version']} posts:" . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
          for($idx = count($post); $idx <= count($bot->forum->posts[$forum_id][$thread_id]['data']); $idx++) {
            $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$idx]['post_id']);
          }
          if (is_array($bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id])) {
            if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id]['post_id'], $post[$_post_id])) $bot->log("Doku forum '{$thread_name}'/{$thread_id}/{$_post_id}: edit post error!");
          } else {
            if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post[$_post_id])) $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
          }
        }
      }
    } else $bot->add_privmsg("Doku error: no forum '{$doku_name}'");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

if(!function_exists('load_doku')) {
  function load_doku($reload = false) {
    global $bot;
    $dokus = array();
    $dirh = opendir(DOKU_DATA);
    while ($file = readdir($dirh)) {
      if (substr($file, -4) == ".xml") {
        if ($reload) $bot->log("Reload doku: ".$file);
        else $bot->log("Load doku: ".$file);
        $xml = simplexml_load_file(DOKU_DATA . $file, "SimpleXMLElement", LIBXML_NOCDATA);
        $entrys = array();
        foreach ($xml->entry as $text){
          $entrys[] = str_replace('##BOTNAME##', $bot->bot_user_name, $text);
        } 
        $dokus[] = array(
          'key'       => strval($xml->key),
          'title'     => str_replace('##BOTNAME##', $bot->bot_user_name, trim($xml->title)),
          'date'      => strval($xml->date),
          'version'   => strval($xml->version),
          'lang'      => strval($xml->lang),
          'author'    => strval($xml->author),
          'entrys'    => $entrys
        );
      }
    }
    closedir($dirh);
    return $dokus;
  }
}
?>