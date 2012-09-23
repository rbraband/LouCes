<?php
global $bot;
$bot->add_category('settlers', array(), PUBLICY);
// crons
$bot->add_cron_event(Cron::DAILY,                           // Cron key
                    "DeleteSettlerLawless",                 // command key
                    "LouBot_delete_settler_lawless_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $continents = $redis->SMEMBERS("continents");
  $settler_key = "settler";
  if (is_array($continents)) foreach ($continents as $continent) {
    $continent_key = "continent:{$continent}";
    $redis->DEL("{$settler_key}:{$continent_key}:lawless");
  }
}, 'settlers');

$bot->add_tick_event(Cron::TICK5,                                    // Cron key
                    "GetSettlersUpdate",                             // command key
                    "LouBot_settlers_continent_player_update_cron",  // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $continents = $redis->SMEMBERS("continents");
  $alliance_key = "alliance:{$bot->ally_id}";
  $settler_key = "settler";
  if (!($forum_id = $redis->GET("{$settler_key}:{$alliance_key}:forum:id"))) {
    $forum_id = $bot->forum->get_forum_id_by_name(BOT_SETTLERS_FORUM, true);
    $redis->SET("{$settler_key}:{$alliance_key}:forum:id", $forum_id);
  }
  
  sort($continents);
  if (is_array($continents) && $bot->forum->exist_forum_id($forum_id)) {
#  if (is_array($continents) && $forum_id) {
    $executeThread = array();
    $childs = array_chunk($continents, MAXCHILDS, true);
    $bot->log("Fork: starting fork " . count($childs) . " childs!");
    foreach($childs as $c_id => $c_continents) {
      // define child
      $bot->lou->check();
      $thread = new executeThread("{$settler_key}Thread-" . $c_id);
      $thread->worker = function($_this, $bot, $continents, $forum_id) {
        // working child
        $error = 0;
        $redis = RedisWrapper::getInstance();
        if (!$redis->status()) exit(2);
        $last_update = $redis->SMEMBERS('stats:ContinentPlayerUpdate');
        sort($last_update);
        $last_update = end($last_update);
        $alliance_key = "alliance:{$bot->ally_id}";
        $settler_key = "settler";
        $settler_chunks = 12;
        $str_time = (string)time();
        $bot->log("Fork: " . $_this->getName() .": start");
        foreach ($continents as $continent) {  
          // ** continents
          if ($continent >= 0) {
            $thread_name = 'K'.$continent;
            $bot->debug("Settlers forum {$thread_name}: start");
            $continent_key = "continent:{$continent}";
            if (!($thread_id = $redis->GET("{$settler_key}:{$alliance_key}:forum:{$continent_key}:id"))) {
              $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
              $redis->SET("{$settler_key}:{$alliance_key}:forum:{$continent_key}:id", $thread_id);
            }
            $update = false;
#            if ($thread_id) {
            if ($bot->forum->exist_forum_thread_id($forum_id, $thread_id)) {
              // ** residents
              $residents = array();
              $redis->SINTERSTORE("{$settler_key}:{$alliance_key}:{$continent_key}:_residents", "{$continent_key}:residents", "{$alliance_key}:member");
              $new_residents = $redis->SDIFF("{$settler_key}:{$alliance_key}:{$continent_key}:_residents", "{$settler_key}:{$alliance_key}:{$continent_key}:residents");
              $redis->RENAME("{$settler_key}:{$alliance_key}:{$continent_key}:_residents", "{$settler_key}:{$alliance_key}:{$continent_key}:residents");
              if (!empty($new_residents)) $update = true;
              $residents = $redis->SMEMBERS("{$settler_key}:{$alliance_key}:{$continent_key}:residents");
              
              // ** settlers
              $settlers = array();
              $settle_strings = array();
              // find 'settler:continent:10:settlers:123:123' name
              $settler_pattern = "{$settler_key}:{$alliance_key}:{$continent_key}:settlers:";
              $settler_keys = $redis->clearKey($redis->Keys("{$settler_pattern}*"), "/{$settler_pattern}/");
              // clear up '123:123' name
              if (is_array($settler_keys)) foreach($settler_keys as $key) {
                if ($settler = $redis->GET("{$settler_pattern}{$key}")) {
                  $settletime = date('d.m.Y H:i:s', time() - (SETTLERTTL - $redis->TTL("{$settler_pattern}{$key}")));
                  $settlers[$key] = array($settler, $settletime);
                  $settle_strings[$key] = "[b][stadt]{$key}[/stadt][/b] ⇒ [spieler]{$settler}[/spieler], ♔ Baron läuft seit: [i]{$settletime}[/i]";
                  $redis->SADD("{$settler_key}:{$alliance_key}:{$continent_key}:_settler", "{$key}|{$settler}");
                }
              }
              $new_settler = $redis->SDIFF("{$settler_key}:{$alliance_key}:{$continent_key}:_settler", "{$settler_key}:{$alliance_key}:{$continent_key}:settler");
              $redis->RENAME("{$settler_key}:{$alliance_key}:{$continent_key}:_settler", "{$settler_key}:{$alliance_key}:{$continent_key}:settler");
              if (!empty($new_settler)) $update = true;
              
              // ** lawless
              $lawless = array();
              $redis->SDIFFSTORE("{$settler_key}:{$continent_key}:_lawless", "{$continent_key}:lawless", "{$settler_key}:{$continent_key}:_lawless");
              $new_lawless = $redis->SDIFF("{$settler_key}:{$continent_key}:_lawless", "{$settler_key}:{$continent_key}:lawless");
              $redis->RENAME("{$settler_key}:{$continent_key}:_lawless", "{$settler_key}:{$continent_key}:lawless");
              if (!empty($new_lawless)) $update = true;
              $lawless = $redis->SMEMBERS("{$settler_key}:{$continent_key}:lawless");

              if (!empty($lawless)) foreach($lawless as $k => $v) {
                $city_id = $redis->HGET('cities', $v);
                $city_key = "city:{$city_id}";
                $city_data = $redis->HGETALL("{$city_key}:data");
                if ($city_data['user_id'] == 0) {
                  $user_name = $redis->HGET("user:{$city_data['ll_user_id']}:data", 'name');
                  $ally_name = $redis->HGET("alliance:{$city_data['ll_alliance_id']}:data", 'name');
                  $lawless[$k] = "[b]{$city_data['category']}[/b] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}/{$city_data['ll_points']}) - [i]{$city_data['ll_name']}[/i] - [s][spieler]{$user_name}[/spieler][/s]" . (($ally_name) ? "[s][[allianz]{$ally_name}[/allianz]][/s]":"");
                  if (is_array($settlers[$v])) {
                    $lawless[$k] = '☒ ' . $lawless[$k] . "
 ⇒ [spieler]{$settlers[$v][0]}[/spieler], ♔ Baron läuft seit: [i]{$settlers[$v][1]}[/i]";
                    unset($settle_strings[$v]);
                  } else $lawless[$k] = '☐ ' . $lawless[$k];
                } else {
                  $user_name = $redis->HGET("user:{$city_data['user_id']}:data", 'name');
                  $ally_name = $redis->HGET("alliance:{$city_data['alliance_id']}:data", 'name');
                  $lawless[$k] = "[b]{$city_data['category']}[/b] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}/{$city_data['ll_points']}) - [i]{$city_data['name']}[/i] - [spieler]{$user_name}[/spieler]" . (($ally_name) ? "[[allianz]{$ally_name}[/allianz]]":"");
                  if (is_array($settlers[$v])) {
                    if ($settlers[$v][0] == $user_name) {
                    $lawless[$k] = '☑ ' . $lawless[$k] . "
 ⇒ [spieler]{$settlers[$v][0]}[/spieler], ♔ Baron lief seit: [i]{$settlers[$v][1]}[/i]";
                    } else {
                    $lawless[$k] = '☒ ' . $lawless[$k] . "
 ⇒ [s][spieler]{$settlers[$v][0]}[/spieler], ♔ Baron lief seit: [i]{$settlers[$v][1]}[/i][/s]";
                    }
                    unset($settle_strings[$v]);
                  }
                  else $lawless[$k] = '☒ ' . $lawless[$k];
                }
              }
              
              // ** create and/or edit
              // new first post = residents
// post txt
$post_residents = "[b][u]{$bot->ally_shortname} Spieler auf dem Kontinent:[/u] {$thread_name}[/b]

".((!empty($residents)) ? "[spieler]".implode('[/spieler]; [spieler]', $residents)."[/spieler]" : "[i]keine Spieler[/i]").'

';
              // ** forum
              $post = array();
              $_post_id = 0;
              $post[$_post_id ++] = $post_residents;
              // new second post = lawless
// post txt
$post_lawless_head = "[b][u]Lawless auf dem Kontinent:[/u] {$thread_name}[/b]

";
$post_lawless_footer = '

[u]Legende[/u]: ☐ - [i]frei[/i], ☒ - [i]nicht frei[/i], ☑ - [i]eledigt![/i]';
              $chunks = array();
              $post_lawless = array();
              if (!empty($lawless)) {
                $chunks = array_chunk($lawless, $settler_chunks);
                if (is_array($chunks)) foreach($chunks as $page => $law) {
                  $post_lawless[$page] = ($page == 0) ? $post_lawless_head : "";
                  $post_lawless[$page] .= implode("
", $law);
                  $post_lawless[$page] .= ($page == (count($chunks)-1)) ? $post_lawless_footer : "";
                }
              } else $post_lawless[] = $post_lawless_head . "[i]keine Lawless[/i]" . $post_lawless_footer;

              // ** forum
              foreach($post_lawless as $_post_lawless) {
                $post[$_post_id ++] = $_post_lawless;
              }
          
              // new third post = settlers
// post txt
$post_settlers_head = "[b][u]Siedler auf dem Kontinent:[/u] {$thread_name}[/b]

";
$post_settlers_footer = '

';
              $chunks = array();
              $post_settlers = array();
              if (!empty($settle_strings)) {
                $chunks = array_chunk($settle_strings, $settler_chunks);
                if (is_array($chunks)) foreach($chunks as $page => $settle) {
                  $post_settlers[$page] = ($page == 0) ? $post_settlers_head : "";
                  $post_settlers[$page] .= implode("
", $settle);
                  $post_settlers[$page] .= ($page == (count($chunks)-1)) ? $post_settlers_footer : "";
                }
              } else $post_settlers[] = $post_settlers_head . "[i]keine Siedler[/i]" . $post_settlers_footer;

              // ** forum
              foreach($post_settlers as $_post_settlers) {
                $post[$_post_id ++] = $_post_settlers;
              }

              // new last post = update
              // post txt
              $post_update = "[u]letztes Update:[/u] [i]" . date('d.m.Y H:i:s', $str_time) . "[/i] | [u]Datenbank:[/u] [i]" . date('d.m.Y H:i:s', $last_update) . "[/i]" . (($update) ? ' | (R:'.count($new_residents).'|LL:'.count($new_lawless).'|S:'.count($new_settler).')':'');
              
              // ** forum            
              foreach ($post as $_post_id_post => $_post) {
                // @internal: edit available posts
                if ($_id = $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $_post_id_post)) {
                  if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $_id, $_post)) {
                    $bot->log("Settlers forum {$thread_name}/{$thread_id}/{$_post_id_post}: edit post error!");
                    $bot->debug($_post);
                    $error = 3;
                  }
                } else {
                  // @internal: or create new ones
                  if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $_post)) {
                    $bot->log("Settlers forum {$thread_name}/{$thread_id}: create post error!");
                    $bot->debug($_post);
                    $error = 3;
                  }
                }
              }
              $_posts_count = $bot->forum->get_thread_post_count($forum_id, $thread_id);
              if ($update && $_posts_count >= count($post)) {
                $bot->log("Settlers forum {$thread_name}: update(R:".count($new_residents).'|LL:'.count($new_lawless).'|S:'.count($new_settler).') posts:' . $_posts_count . '|' . count($post));
                // @internal: on update we delete the last post and create a new one with $post_update to signal a new post/update ingame
                for($idx = count($post); $idx <= $_posts_count; $idx ++) {
                  $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $idx));
                }
                if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post_update)) {
                  $bot->log("Settlers forum {$thread_name}/{$thread_id}: create post error!");
                  $bot->debug($post_update);
                  $error = 3;
                }
              } else {
                $bot->log("Settlers forum {$thread_name}: info(R:".count($new_residents).'|LL:'.count($new_lawless).'|S:'.count($new_settler).') posts:' . $_posts_count . '|' . count($post));
                $post[$_post_id] = $post_update;
                // @internal: otherwise update the last post with $post_update
                $bot->log("Settlers forum {$thread_name}: info(".count($new_residents).'|'.count($new_lawless).'|'.count($new_settler).') posts:' . $_posts_count . '|' . count($post));
                for($idx = count($post); $idx <= $_posts_count; $idx ++) {
                  $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $idx));
                }
                if ($_id = $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $_post_id)) {
                  if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $_id, $post[$_post_id])) {
                    $bot->log("Settlers forum {$thread_name}/{$thread_id}/{$_post_id}: edit post error!");
                    $bot->debug($post[$_post_id]);
                    $error = 3;
                  }
                } else {
                  if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post[$_post_id])) {
                    $bot->log("Settlers forum {$thread_name}/{$thread_id}: create post error!");
                    $bot->debug($post[$_post_id]);
                    $error = 3;
                  }
                }
              }
            } else {
              $error = 4;
              $bot->log("Settlers forum {$thread_name}: error!");
              $redis->DEL("{$settler_key}:{$alliance_key}:forum:{$continent_key}:id");
            }
          }
        }
        exit($error);
      }; 
      $thread->start($thread, $bot, $c_continents, $forum_id);
      $bot->debug("Started " . $thread->getName() . " with PID " . $thread->getPid() . "...");
      array_push($executeThread, $thread);
    }
    foreach($executeThread as $thread) {
      pcntl_waitpid($thread->getPid(), $status, WUNTRACED);
      $bot->debug("Stopped " . $thread->getPid() . '@'. $thread->getName() . (!pcntl_wifexited($status) ? ' with' : ' without') . " errors!");
      if (pcntl_wifsignaled($status)) $bot->log($thread->getPid() . '@'. $thread->getName() . " stopped with state #" . pcntl_wexitstatus($status) . " errors!");
    }
    $bot->log("Fork: closing, all childs done!");
    unset($executeThread);
    $redis->reInstance();
  } else {
    $bot->log("Settler error: no forum '" . BOT_SETTLERS_FORUM . "'");
    $redis->DEL("{$settler_key}:{$alliance_key}:forum:id");
  }
}, 'settlers');

// callbacks
$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                   "Siedeln",               // command key
                   "LouBot_settler",        // callback function
                   false,                   // is a command PRE needet?
                   '/^[!]?(settle|si[e]?del[n]?|lawle[s]{1,2}|ll)$/i',       // optional regex for key
function ($bot, $data) {
  global $redis, $sms;
  if (!$redis->status()) return;
  $commands = array('off', 'on', 'del', 'mail');
  $alliance_key = "alliance:{$bot->ally_id}";
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    if (in_array(strtolower($data['params'][0]), $commands)) {
      $continents = $redis->SMEMBERS("continents");
      $second_argument = strtolower(Lou::prepare_chat($data['params'][1]));
      $settler_key = "settler";
      switch (strtolower($data['params'][0])) {
        case 'off':
          if ($data['command'][0] == PRE) {
            if ($redis->SADD("{$settler_key}:{$alliance_key}:nomail", $data['user'])) {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist aus dem Mailverteiler abgemeldet!";
              else $message = 'Du bist aus dem Mailverteiler abgemeldet!';
            } else if ($redis->SISMEMBER("{$settler_key}:{$alliance_key}:nomail", $data['user'])) {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist aus dem Mailverteiler abgemeldet!";
              else $message = 'Du bist aus dem Mailverteiler abgemeldet!';
            }
          }
          break;
        case 'on':
          if ($data['command'][0] == PRE) {
            if ($redis->SREM("{$settler_key}:{$alliance_key}:nomail", $data['user'])) {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist im Mailverteiler angemeldet!";
              else $message = 'Du bist im Mailverteiler angemeldet!';
            } else if (!$redis->SISMEMBER("{$settler_key}:{$alliance_key}:nomail", $data['user'])) {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist im Mailverteiler angemeldet!";
              else $message = 'Du bist im Mailverteiler angemeldet!';
            }
          }
          break;
        case 'mail':
          if ($data['command'][0] == PRE) {
            if ($redis->SISMEMBER("{$settler_key}:{$alliance_key}:nomail", $data['user'])) {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist aus dem Mailverteiler abgemeldet!";
              else $message = 'Du bist aus dem Mailverteiler abgemeldet!';
            } else {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist im Mailverteiler angemeldet!";
              else $message = 'Du bist im Mailverteiler angemeldet!';
            }
          }
          break;
        case 'del':
          if ($data['command'][0] == PRE && Lou::is_string_pos(Lou::prepare_chat($second_argument))) {// is position?
            $pos = Lou::get_pos_by_string(Lou::prepare_chat($second_argument));
            $continent = $bot->lou->get_continent_by_pos($pos);
            $continent_key = "continent:{$continent}";
            $continent_name = "[u]K{$continent}[/u]";
            $settler = $redis->GET("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}");
            $lawless = ($redis->SISMEMBER("{$continent_key}:lawless", $pos)) ? 'LL ' : '';
            if (!$settler || $settler != $data['user']) {
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du siedelst nicht auf {$continent_name} {$lawless}[coords]{$pos}[/coords]";
              else $message = "Du siedelst nicht auf {$continent_name} {$lawless}[coords]{$pos}[/coords]";
            } else {
              $redis->DEL("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}");
              if ($data["channel"] == ALLYIN) $message = "{$data['user']}, siedeln auf {$continent_name} {$lawless}[coords]{$pos}[/coords] gelöscht!";
              else $message = "Siedeln auf {$continent_name} {$lawless}[coords]{$pos}[/coords] gelöscht!";
              $receivers = $redis->SDIFF("{$settler_key}:{$alliance_key}:{$continent_key}:residents", "{$settler_key}:{$alliance_key}:nomail");
              // find 'settler:continent:10:settlers:123:123' name
              $settler_pattern = "{$settler_key}:{$alliance_key}:{$continent_key}:settlers:";
              $settler_keys = $redis->clearKey($redis->Keys("{$settler_pattern}*"), "/{$settler_pattern}/");
              // clear up '123:123' name
              if (is_array($settler_keys)) foreach($settler_keys as $key) {
                if ($name = $redis->GET("{$settler_pattern}{$key}"))
                  if (!in_array($name, $receivers) && !$redis->SISMEMBER("{$settler_key}:{$alliance_key}:nomail", $name)) $receivers[] = $name;
              }
              if (!in_array($data['user'], $receivers) && !$redis->SISMEMBER("{$settler_key}:{$alliance_key}:nomail", $data['user'])) $receivers[] = $data['user'];
              $bot->log('IGM: send '.count($receivers).' messages to K'.$continent);
              $bot->igm->send(implode(';',$receivers), "♚ K{$continent} {$pos} {$lawless}gelöscht!", "K{$continent} - {$lawless}[coords]{$pos}[/coords] - von [spieler]{$data['user']}[/spieler] gelöscht");
            }
          } else {
            $message = 'Siedeln Fehler: falsche Parameter!';
          }
          break;
      }
    } else if (Lou::is_string_pos(Lou::prepare_chat($data['params'][0]))) {// is position?
      $pos = Lou::get_pos_by_string(Lou::prepare_chat($data['params'][0]));
      $continent = $bot->lou->get_continent_by_pos($pos);
      $settler_key = "settler";
      $continent_key = "continent:{$continent}";
      $continent_name = "[u]K{$continent}[/u]";
      if ($data['command'][0] == PRE) {
        // set 'settler:continent:10:settlers:123:123' name
        if (preg_match('/^!(lawle[s]{1,2}|ll)$/i', $data['command']) && !$redis->SISMEMBER("{$continent_key}:lawless", $pos)) {
          $str_time = (string)time();
          $redis->SADD("{$continent_key}:lawless", $pos);
          $city_id = $redis->HGET('cities', $pos);
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
        if ($redis->SETNX("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}", $data['user'])) {
          $redis->EXPIRE("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}", SETTLERTTL);
          $lawless = ($redis->SISMEMBER("{$continent_key}:lawless", $pos)) ? 'LL ' : '';
          if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du siedelst nun auf {$continent_name} {$lawless}[coords]{$pos}[/coords]";
          else $message = "Du siedelst nun auf {$continent_name} {$lawless}[coords]{$pos}[/coords]";
          $receivers = $redis->SDIFF("{$settler_key}:{$alliance_key}:{$continent_key}:residents", "{$settler_key}:{$alliance_key}:nomail");
          // find 'settler:continent:10:settlers:123:123' name
          $settler_pattern = "{$settler_key}:{$alliance_key}:{$continent_key}:settlers:";
          $settler_keys = $redis->clearKey($redis->Keys("{$settler_pattern}*"), "/{$settler_pattern}/");
          // clear up '123:123' name
          if (is_array($settler_keys)) foreach($settler_keys as $key) {
            if ($name = $redis->GET("{$settler_pattern}{$key}"))
              if (!in_array($name, $receivers) && !$redis->SISMEMBER("{$settler_key}:{$alliance_key}:nomail", $name)) $receivers[] = $name;
          }
          $bot->log('IGM: send '.count($receivers).' messages to K'.$continent);
          $bot->igm->send(implode(';',$receivers), "♔ K{$continent} {$pos} {$lawless}siedeln", "K{$continent} - {$lawless}[coords]{$pos}[/coords] - [spieler]{$data['user']}[/spieler]");
        } else {
          $settler = $redis->GET("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}");
          $lawless = ($redis->SISMEMBER("{$continent_key}:lawless", $pos)) ? 'LL ' : '';
          $settletime = date('d.m.Y H:i:s', time() - (SETTLERTTL - $redis->TTL("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}")));
          if ($settler != $data['user']) $message = "[spieler]{$settler}[/spieler] siedelt auf {$continent_name} {$lawless}[coords]{$pos}[/coords] seit: {$settletime}";
          else {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du siedelst schon auf {$continent_name} {$lawless}[coords]{$pos}[/coords] seit: {$settletime}";
            else $message = "Du siedelst schon auf {$continent_name} {$lawless}[coords]{$pos}[/coords] seit: {$settletime}";
          }
        }
      } else {
        if ($settler = $redis->GET("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}")) {
          $lawless = ($redis->SISMEMBER("{$continent_key}:lawless", $pos)) ? 'LL ' : '';
          $settletime = date('d.m.Y H:i:s', time() - (SETTLERTTL - $redis->TTL("{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}")));
          $message = "[spieler]{$settler}[/spieler] siedelt auf {$continent_name} {$lawless}[coords]{$pos}[/coords] seit: {$settletime}";
        } else $message = "niemand siedelt auf {$continent_name} [coords]{$pos}[/coords]!";
      }
    } else if ($data['command'][0] == PRE) $bot->add_privmsg('Siedeln Fehler: falsche Parameter ('.Lou::prepare_chat($data['params'][0]).')!', $data['user']);
    else return;
    if ($data["channel"] == ALLYIN)
      $bot->add_allymsg($message);
    else 
      $bot->add_privmsg($message, $data['user']);
    return true;
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'settlers');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "AddLawless",            // command key
                       "LouBot_add_lawless",    // callback function
                       true,                    // is a command PRE needet?
                       '/(addlawless|addll)/i', // optional regex for key
function ($bot, $data) {
  global $redis;
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    $message = "Add lawless:";
    if (is_array($data['params'])) foreach($data['params'] as $ll) {
      if (Lou::is_string_pos(Lou::prepare_chat($ll))) {// is position?
        $pos = Lou::get_pos_by_string(Lou::prepare_chat($ll));
        $continent = $bot->lou->get_continent_by_pos($pos);
        $continent_key = "continent:{$continent}";
        if ($redis->SADD("{$continent_key}:lawless", $pos)) {
          $message .= " {$pos}|Ok";
          $str_time = (string)time();
          $city_id = $redis->HGET('cities', $pos);
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
        } else $message .= " {$pos}|Nok";
      }
    } else $message .= ' falsche Parameter!';
    $bot->add_privmsg($message, $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'settlers');

$bot->add_privmsg_hook("RebaseSettleForum",           // command key
                       "LouBot_rebase_settle_forum",  // callback function
                       true,                          // is a command PRE needet?
                       '',                            // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if($bot->is_op_user($data['user'])) {
    $continents = $redis->SMEMBERS("continents");
    $alliance_key = "alliance:{$bot->ally_id}";
    $settler_key = "settler";
    
    if (!($forum_id = $redis->GET("{$settler_key}:{$alliance_key}:forum:id"))) {
      $forum_id = $bot->forum->get_forum_id_by_name(BOT_SETTLERS_FORUM, true);
    } else $redis->DEL("{$settler_key}:{$alliance_key}:forum:id");
    sort($continents);
    if (is_array($continents) && $bot->forum->exist_forum_id($forum_id)) {
      foreach ($continents as $continent) {
        // ** continents
        if ($continent >= 0) {
          $thread_name = 'K'.$continent;
          $bot->debug("Settlers forum {$thread_name}: delete");
          $continent_key = "continent:{$continent}";
          if (!($thread_id = $redis->GET("{$settler_key}:{$alliance_key}:forum:{$continent_key}:id"))) {
            $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
            $redis->SET("{$settler_key}:{$alliance_key}:forum:{$continent_key}:id", $thread_id);
          } else $redis->DEL("{$settler_key}:{$alliance_key}:forum:{$continent_key}:id");
          $thread_ids[] = $thread_id;
        }
      }
      if ($bot->forum->delete_alliance_forum_threads($forum_id, $thread_ids)) {
        $bot->add_privmsg("Step1# ".BOT_SETTLERS_FORUM." deleted!", $data['user']);
        $bot->call_event(array('type' => TICK, 'name' => Cron::TICK5), 'LouBot_settlers_continent_player_update_cron');
        $bot->add_privmsg("Step2# ".BOT_SETTLERS_FORUM." rebase done!", $data['user']);
      }
      else $bot->add_privmsg("Fehler beim löschen von: ".BOT_SETTLERS_FORUM."", $data['user']);
    }
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("ReloadSettleForum",           // command key
                       "LouBot_reload_settle_forum",  // callback function
                       true,                          // is a command PRE needet?
                       '',                            // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if($bot->is_op_user($data['user'])) {
    $alliance_key = "alliance:{$bot->ally_id}";
    $settler_key = "settler";
    $settler_key_keys = $redis->getKeys("{$settler_key}:{$alliance_key}:forum:*");
    if (!empty($settler_key_keys)) foreach($settler_key_keys as $settler_key_key) {
      $redis->DEL("{$settler_key_key}");
    }
    $bot->add_privmsg("Step1# ".BOT_SETTLERS_FORUM." REDIS ids deleted!", $data['user']);
    $bot->call_event(array('type' => TICK, 'name' => Cron::TICK5), 'LouBot_settlers_continent_player_update_cron');
    $bot->add_privmsg("Step2# ".BOT_SETTLERS_FORUM." reload done!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');                   
?>