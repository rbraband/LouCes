<?php
global $bot;
$bot->add_category('doku', array(), PUBLICY);
// crons
$bot->add_thread_event(Cron::HOURLY,                    // Cron key
                    "GetDokuUpdate",                  // command key
                    "LouBot_doku_update_cron",        // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  update_doku($bot);
}, 'doku');

$bot->add_privmsg_hook("UpdateDoku",         		// command key
                       "LouBot_update_doku", 		// callback function
                       true,                 		// is a command PRE needet?
                       '', 	                    // optional regex for key
function ($bot, $data) {
  global $redis;
	if($bot->is_op_user($data['user'])) {
    update_doku($bot);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');
  
if (!function_exists('update_doku')) {
  function update_doku($bot) {
    global $redis;
    $alliance_key = "alliance:{$bot->ally_id}";
    $doku_key = "doku";
    $str_time = (string)time();
    $doku_name = "{$bot->bot_user_name}'s World";
    if (!($forum_id = $redis->GET("{$doku_key}:{$alliance_key}:forum:id"))) {
     $forum_id = $bot->forum->get_forum_id_by_name($doku_name, true);
     $redis->SET("{$doku_key}:{$alliance_key}:forum:id", $forum_id);
    }
    $last_update = $redis->SMEMBERS('stats:DokuUpdate');
    sort($last_update);
    $last_update = end($last_update);
    $dokus = load_doku();
    if (is_array($dokus) && $bot->forum->exist_forum_id($forum_id)) foreach ($dokus as $doku) {
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
      if ($bot->forum->exist_forum_thread_id($forum_id, $thread_id) && ($update || $force)) {
      // ** forum
      $redis->SET("{$doku_key}:{$alliance_key}:forum:{$doku['key']}:version", $doku['version']);
        $post = array();
      $_post_id = 0;
      foreach($doku['entrys'] as $entry) {
          $post[$_post_id ++] = $entry;
      }
      $post_update = "[u]letztes Update:[/u] [i]{$doku['date']}[/i] | [u]Dokumentation:[/u] [spieler]{$doku['author']}[/spieler]";
        // ** forum            
      foreach ($post as $_post_id_post => $_post) {
          if ($_id = $bot->forum->get_thread_post_by_num($forum_id, $thread_id, $_post_id_post)) {
            if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $_id, $_post)) {
              $bot->log("Doku forum '{$thread_name}'/{$thread_id}/{$_post_id_post}: edit post error!");
              $bot->debug($_post);
              $error = 3;
            }
        } else {
            if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $_post)) {
              $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
              $bot->debug($_post);
              $error = 3;
        }
      }
        }
        $_posts_count = $bot->forum->get_thread_post_count($forum_id, $thread_id);  
        if ($update && $_posts_count >= count($post)) {
        $bot->log("Doku forum '{$thread_name}': update({$doku['date']}) v{$doku['version']} posts:" . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
          for($idx = count($post); $idx <= $_posts_count; $idx++) {
            $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->get_thread_post_by_num($forum_id, $thread_id, $idx));
          }
          if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post_update)) {
            $bot->log("Doku forum '{$thread_name}'/{$thread_id}: create post error!");
            $bot->debug($post_update);
            $error = 3;
        }
      } else {
        $post[$_post_id] = $post_update;
        $bot->log("Doku forum '{$thread_name}': update({$doku['date']}) v{$doku['version']} posts:" . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
          for($idx = count($post); $idx <= $_posts_count; $idx++) {
            $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->get_thread_post_by_num($forum_id, $thread_id, $idx));
          }
          if ($_id = $bot->forum->get_thread_post_by_num($forum_id, $thread_id, $_post_id)) {
            if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $_id, $post[$_post_id])) {
              $bot->log("Doku forum '{$thread_name}'/{$thread_id}/{$_post_id}: edit post error!");
              $bot->debug($post[$_post_id]);
              $error = 3;
        }
        } else {
            if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post[$_post_id])) {
              $bot->log("Doku forum {$thread_name}/{$thread_id}: create post error!");
              $bot->debug($post[$_post_id]);
              $error = 3;
        }
      }
    }
      }
    } else {
      $error = 4;
      $bot->log("Doku error: no forum '{$doku_name}'");
      $redis->del("{$doku_key}:{$alliance_key}:forum:id");
    }
  }
}

$bot->add_privmsg_hook("ReloadDokuForum",             // command key
                       "LouBot_reload_duko_forum",    // callback function
                       true,                   // is a command PRE needet?
                       '',                     // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if($bot->is_op_user($data['user'])) {
    $alliance_key = "alliance:{$bot->ally_id}";
    $doku_key = "doku";
    $doku_key_keys = $redis->getKeys("{$doku_key}:{$alliance_key}:forum:*");
    if (!empty($doku_key_keys)) foreach($doku_key_keys as $doku_key_key) {
      $redis->del("{$doku_key_key}");
    }
    $doku_name = "{$bot->bot_user_name}'s World";
    $bot->add_privmsg("Step1# ".$doku_name." REDIS ids deleted!", $data['user']);
    $bot->call_event(array('type' => TICK, 'name' => Cron::HOURLY), 'LouBot_doku_update_cron');
    $bot->add_privmsg("Step2# ".$doku_name." reload done!", $data['user']);
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