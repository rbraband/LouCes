<?php
global $bot;
$bot->add_category('statistic', array(), PUBLICY);
// crons
$bot->add_cron_event(Cron::HOURLY,                            // Cron key
                    "GetMilitaryUpdate",                      // command key
                    "LouBot_military_continent_update_cron",  // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $continents = $redis->SMEMBERS("continents");
  $alliance_key = "alliance:{$bot->ally_id}";
  $military_key = "military";
  $settler_key = "settler";
  if (!($forum_id = $redis->GET("{$military_key}:{$alliance_key}:forum:id"))) {
    $forum_id = $bot->forum->get_forum_id_by_name(BOT_STATISTICS_FORUM, true);
    $redis->SET("{$military_key}:{$alliance_key}:forum:id", $forum_id);
  }
  
  sort($continents);
  if (is_array($continents) && $bot->forum->exist_forum_id($forum_id)) {
    $executeThread = array();
    $childs = array_chunk($continents, MAXCHILDS, true);
    $bot->log("Fork: starting fork " . count($childs) . " childs!");
    foreach($childs as $c_id => $c_continents) {
      // define child
      $bot->lou->check();
      $thread = new executeThread("{$military_key}Thread-" . $c_id);
      $thread->worker = function($_this, $bot, $continents, $forum_id) {
        // working child
        $error = 0;
        $redis = RedisWrapper::getInstance($_this->getPid());
        $last_update = $redis->SMEMBERS('stats:ContinentPlayerUpdate');
        sort($last_update);
        $last_update = end($last_update);
        $alliance_key = "alliance:{$bot->ally_id}";
        $military_key = "military";
        $settler_key = "settler";
        $military_chars = 2900; 
        $str_time = (string)time();
        $bot->log("Fork: " . $_this->getName() .": start");
        foreach ($continents as $continent) {
          // ** continents
          if ($continent >= 0) {
            $thread_name = 'K'.$continent;
            $bot->debug("Military forum {$thread_name}: start");
            $continent_key = "continent:{$continent}";
            if (!($thread_id = $redis->GET("{$military_key}:{$alliance_key}:forum:{$continent_key}:id"))) {
              $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
              $redis->SET("{$military_key}:{$alliance_key}:forum:{$continent_key}:id", $thread_id);
            }
            $update = false;
            $off_entrys = array();
            $deff_entrys = array();
            $castles = array();
            $wcastles = array();
            $palasts = array();
            $pcities = array();
            $ally_castle_user = array();
            $ally_wcastle_user = array();
            $ally_palast_user = array();
            $ally_cities_user = array();
            $post_residents = array();
            $post_ally = array();
            $post_chunks = array();
            if ($thread_id) {
            #if ($bot->forum->exist_forum_thread_id($forum_id, $thread_id)) {
            
              // ** residents
              $residents = $redis->SMEMBERS("{$settler_key}:{$alliance_key}:{$continent_key}:residents");
              // ** military top ten offence
              $offence = $redis->HGETALL("{$continent_key}:offence");
              $cities = $redis->SMEMBERS("{$continent_key}:cities");
              if (is_array($cities)) foreach($cities as $city) {
                $_city = $redis->HGETALL('city:'.$redis->HGET('cities', $city).':data');
                if ($_city['state'] == 0) {
                  $pcities[$_city['alliance_id']]++;
                  $ally_cities_user[$_city['alliance_id']][$_city['user_id']] = $redis->HGET("user:{$_city['user_id']}:data", 'name');
                }
                elseif ($_city['state'] == 1) {
                  if ($_city['water'] == 1) $wcastles[$_city['alliance_id']]++;
                  else $castles[$_city['alliance_id']]++;
                  if ($_city['water'] == 1 && empty($ally_wcastle_user[$_city['alliance_id']][$_city['user_id']])) {
                    $ally_wcastle_user[$_city['alliance_id']][$_city['user_id']] = $redis->HGET("user:{$_city['user_id']}:data", 'name');
                  }
                  else if ($_city['water'] == 0 && empty($ally_castle_user[$_city['alliance_id']][$_city['user_id']])) {
                    $ally_castle_user[$_city['alliance_id']][$_city['user_id']] = $redis->HGET("user:{$_city['user_id']}:data", 'name');
                  }
                }
                elseif ($_city['state'] == 2) {
                  $palasts[$_city['alliance_id']]++;
                  if (empty($ally_palast_user[$_city['alliance_id']][$_city['user_id']])) {
                    $ally_palast_user[$_city['alliance_id']][$_city['user_id']] = $redis->HGET("user:{$_city['user_id']}:data", 'name');
                  }
                }
              }
              $sum_ts = 0;
              $ts = array();
              $alliance = array();
              if (is_array($offence)) foreach($offence as $k => $v) {
                $val = explode('|', $v);
                $ts[$k] += $val[0];
                $sum_ts += $val[0];
                $alliance[$k] = array($k,$val[0],$val[1]);
              }
              $sum_ts_string = preg_replace("/(?<=\d)(?=(\d{3})+(?!\d))/", ".", $sum_ts);
              /*
              $one_point = $sum_ts / 100;
              $st = array_flip($ts);
              krsort($st);
              $output = array_slice($st, 0, 10);
              if (is_array($output)) foreach($output as $i) {
                $item = $alliance[$i];
                $tsstring = preg_replace("/(?<=\d)(?=(\d{3})+(?!\d))/", ".", $item[1]);
                $percent = round($item[1]/$one_point, 2);
                $ally_name = $redis->HGET("alliance:{$item[0]}:data", 'name');
                $ally_castles = ($castles[$item[0]]) ? $castles[$item[0]] : 0;
                $ally_palasts = ($palasts[$item[0]]) ? $palasts[$item[0]] : 0;
                $ally_name = (!$ally_name) ? 'Anonym' : "[allianz]{$ally_name}[/allianz]";
                $off_entrys[] = str_pad("*",ceil($percent),"|") . "
 ⇒ {$percent}% [i]{$ally_name}[/i] ({$item[2]} Off-Spieler, [b]{$tsstring}[/b] TS, {$ally_castles} Burgen, {$ally_palasts} Palaste)";
              }*/
                           
              // ** Ally Castles
              // ** create and/or edit
              // new first post = residents
// post txt
$post_residents[0] = "[b][u]{$bot->ally_shortname} Off-Spieler auf dem Kontinent:[/u] {$thread_name}[/b]
Burgen: (".((!empty($castles[$bot->ally_id])) ? $castles[$bot->ally_id] : 0).")
".((!empty($ally_castle_user[$bot->ally_id])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_castle_user[$bot->ally_id]))."[/spieler]" : "[i]keine Spieler[/i]")."
";
$post_residents[1] = "
Wasserburgen: (".((!empty($wcastles[$bot->ally_id])) ? $wcastles[$bot->ally_id] : 0).")
".((!empty($ally_wcastle_user[$bot->ally_id])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_wcastle_user[$bot->ally_id]))."[/spieler]" : "[i]keine Spieler[/i]")."
";
$post_residents[2] = "
Palaste: (".((!empty($palasts[$bot->ally_id])) ? $palasts[$bot->ally_id] : 0).")
".((!empty($ally_palast_user[$bot->ally_id])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_palast_user[$bot->ally_id]))."[/spieler]" : "[i]keine Spieler[/i]")."
";

$post_residents[3] = "
[b][u]{$bot->ally_shortname} Deff-Spieler auf dem Kontinent:[/u] {$thread_name}[/b]
Städte: (".((!empty($pcities[$bot->ally_id])) ? $pcities[$bot->ally_id] : 0).")
".((!empty($ally_cities_user[$bot->ally_id])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_cities_user[$bot->ally_id]))."[/spieler]" : "[i]keine Spieler[/i]")."
";
              
              // ** create and/or edit
              // new second post = offence
              
// post txt
$post_topten = "[b][u]Top Off-TS auf dem Kontinent:[/u] {$thread_name} ({$sum_ts_string} TS)[/b]";
/*
".((!empty($off_entrys)) ? implode("
", $off_entrys) : "[i]keine TS[/i]").'

';*/              
              // ** military top ten defence
              $defence = $redis->HGETALL("{$continent_key}:defence");
              $sum_ts = 0;
              $ts = array();
              $alliance = array();
              if (is_array($defence)) foreach($defence as $k => $v) {
                $val = explode('|', $v);
                $ts[$k] += $val[0];
                $alliance[$k] = array($k,$val[0],$val[1]);
                $sum_ts += $val[0];
              }
              $sum_ts_string = preg_replace("/(?<=\d)(?=(\d{3})+(?!\d))/", ".", $sum_ts);
              /*
              $st = array_flip($ts);
              krsort($st);
              $output = array_slice($st, 0, 10);
              $one_point = $sum_ts / 100;
              if (is_array($output)) foreach($output as $i) {
                $item = $alliance[$i];
                $tsstring = preg_replace("/(?<=\d)(?=(\d{3})+(?!\d))/", ".", $item[1]);
                $percent = round($item[1]/$one_point, 2);
                $ally_name = $redis->HGET("alliance:{$item[0]}:data", 'name');
                $ally_name = (!$ally_name) ? 'Anonym' : "[allianz]{$ally_name}[/allianz]";
                $ally_cities = ($pcities[$item[0]]) ? $pcities[$item[0]] : 0;
                $deff_entrys[] = str_pad("*",ceil($percent),"|") . "
 ⇒ {$percent}% [i]{$ally_name}[/i] ({$item[2]} Deff-Spieler, [b]{$tsstring}[/b] TS, {$ally_cities} Städte)";
              }*/
              
              // ** create and/or edit
              // new seond post = defence
// post txt
$post_topten_deff = "[b][u]Top Deff-TS auf dem Kontinent:[/u] {$thread_name} ({$sum_ts_string} TS)[/b]";
/*
".((!empty($deff_entrys)) ? implode("
", $deff_entrys) : "[i]keine TS[/i]").'

';*/
              // ** forum
              $post = array();
              $bot->forum->get_alliance_forum_posts($forum_id, $thread_id);
              $_post_id = 0;
              
              for($i = 0, $size = count($post_residents); $i < $size; ++$i) {
                if (strlen($post_residents[$i]) > $military_chars || empty($post_residents[$i + 1]) || strlen($post_residents[$i] . $post_residents[$i + 1]) > $military_chars) {
                  if (strlen($post_residents[$i]) > $military_chars) {
                    $post_chunks = explode('***chunk***', wordwrap ($post_residents[$i], $military_chars, '***chunk***'));
                    for($_i = 0, $_size = count($post_chunks); $_i < $_size; ++$_i) {
                      $post[$_post_id] = $post_chunks[$_i];
                      $_post_id++;
                    }
                  } else {
                    $post[$_post_id] = $post_residents[$i];
                    $_post_id++;
                  }
                } else {
                  $post_residents[$i + 1] = $post_residents[$i] . $post_residents[$i + 1];
                }
              }
              /*
              if (strlen($post_residents[0]) > $military_chars || strlen($post_residents[0] . $post_residents[1]) > $military_chars) {
                $post[$_post_id] = $post_residents[0];
                $_post_id++;
              } else {
                $post_residents[1] = $post_residents[0] . $post_residents[1];
              } 
              if (strlen($post_residents[1]) > $military_chars || strlen($post_residents[1] . $post_residents[2]) > $military_chars) {
                $post[$_post_id] = $post_residents[1];
                $_post_id++;
              } else {
                $post_residents[2] = $post_residents[1] . $post_residents[2];
              }
              if (strlen($post_residents[2]) > $military_chars || strlen($post_residents[2] . $post_residents[3]) > $military_chars) {
                $post[$_post_id] = $post_residents[2];
                $_post_id++;
              } else {
                $post_residents[3] = $post_residents[2] . $post_residents[3];
              }
              $post[$_post_id] = $post_residents[3];
              $_post_id++;
              */
              
              $post[$_post_id] = $post_topten;
              $_post_id++;
              $post[$_post_id] = $post_topten_deff;
              
              if (!empty($ally_castle_user)) foreach($ally_castle_user as $_ally => $_ally_castle_user) {
                // ** Castles
                // ** create and/or edit
                // new first post = residents
                if ($_ally == $bot->ally_id || empty($_ally_castle_user)) continue;
                $ally_name = $redis->HGET("alliance:{$_ally}:data", 'name');
                $ally_name = (!$ally_name) ? 'ohne Allianz' : "[allianz]{$ally_name}[/allianz]";
// post txt
$post_ally[0] = "[b][u]{$ally_name} Off-Spieler auf dem Kontinent:[/u] {$thread_name}[/b]
Burgen: (".((!empty($castles[$_ally])) ? $castles[$_ally] : 0).")
".((!empty($ally_castle_user[$_ally])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_castle_user[$_ally]))."[/spieler]" : "[i]keine Spieler[/i]")."
";
$post_ally[1] = "
Wasserburgen: (".((!empty($wcastles[$_ally])) ? $wcastles[$_ally] : 0).")
".((!empty($ally_wcastle_user[$_ally])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_wcastle_user[$_ally]))."[/spieler]" : "[i]keine Spieler[/i]")."
";
$post_ally[2] = "
Palaste: (".((!empty($palasts[$_ally])) ? $palasts[$_ally] : 0).")
".((!empty($ally_palast_user[$_ally])) ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_palast_user[$_ally]))."[/spieler]" : "[i]keine Spieler[/i]")."
";
$post_ally[3] = "
[b][u]{$ally_name} Deff-Spieler auf dem Kontinent:[/u] {$thread_name}[/b]
Städte: (".((!empty($pcities[$_ally])) ? $pcities[$_ally] : 0).")
".((!empty($ally_cities_user[$_ally]) && $ally_name != 'ohne Allianz') ? "[spieler]".implode('[/spieler]; [spieler]', array_values($ally_cities_user[$_ally]))."[/spieler]" : (($ally_name != 'ohne Allianz') ? "[i]keine Spieler[/i]" : "[i]ohne Auswertung[/i]"))."
";
                $_post_id++;
                for($i = 0, $size = count($post_ally); $i < $size; ++$i) {
                  if (strlen($post_ally[$i]) > $military_chars || empty($post_ally[$i + 1]) || strlen($post_ally[$i] . $post_ally[$i + 1]) > $military_chars) {
                    if (strlen($post_ally[$i]) > $military_chars) {
                      $post_chunks = explode('***chunk***', wordwrap ($post_ally[$i], $military_chars, '***chunk***'));
                      for($_i = 0, $_size = count($post_chunks); $_i < $_size; ++$_i) {
                        $post[$_post_id] = $post_chunks[$_i];
                        $_post_id++;
                      }
                    } else {
                      $post[$_post_id] = $post_ally[$i];
                      $_post_id++;
                    }
                  } else {
                    $post_ally[$i + 1] = $post_ally[$i] . $post_ally[$i + 1];
                  }
                }
                
                /*
                if (strlen($post_ally[0]) > $military_chars || strlen($post_ally[0] . $post_ally[1]) > $military_chars) {
                  $_post_id++;
                  $post[$_post_id] = $post_ally[0];
                } else {
                  $post_ally[1] = $post_ally[0] . $post_ally[1];
                } 
                if (strlen($post_ally[1]) > $military_chars || strlen($post_ally[1] . $post_ally[2]) > $military_chars) {
                  $_post_id++;
                  $post[$_post_id] = $post_ally[1];
                } else {
                  $post_ally[2] = $post_ally[1] . $post_ally[2];
                }
                if (strlen($post_ally[2]) > $military_chars || strlen($post_ally[2] . $post_ally[3]) > $military_chars) {
                  $_post_id++;
                  $post[$_post_id] = $post_ally[2];
                } else {
                  $post_ally[3] = $post_ally[2] . $post_ally[3];
                }
                $_post_id++;
                $post[$_post_id] = $post_ally[3];
                */
              }

              // new last post = update
              // post txt
              $post_update = "[u]letztes Update:[/u] [i]" . date('d.m.Y H:i:s', $str_time) . "[/i] | [u]Datenbank:[/u] [i]" . date('d.m.Y H:i:s', $last_update) . "[/i]";
        
              // ** forum            
              foreach ($post as $_post_id_post => $_post) {
                if (is_array($bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id_post])) {
                  if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id_post]['post_id'], $_post)) {
                    $bot->log("Military forum {$thread_name}/{$thread_id}/{$_post_id_post}: edit post error!");
                    $bot->debug($_post);
                    $error = 3;
                  }
                } else {
                  if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $_post)) {
                    $bot->log("Military forum {$thread_name}/{$thread_id}: create post error!");
                    $bot->debug($_post);
                    $error = 3;
                  }
                }
              }
        
              $_post_id++;
              if ($update && count($bot->forum->posts[$forum_id][$thread_id]['data']) >= count($post)) {
                $bot->log("Military forum {$thread_name}: update(".count($residents).'|'.count($residents2).') posts:' . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
                for($idx = count($post); $idx <= count($bot->forum->posts[$forum_id][$thread_id]['data']); $idx++) {
                  $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$idx]['post_id']);
                }
                if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post_update)) {
                  $bot->log("Military forum {$thread_name}/{$thread_id}: create post error!");
                  $bot->debug($post_update);
                  $error = 3;
                }
              } else {
                $post[$_post_id] = $post_update;
                $bot->log("Military forum {$thread_name}: info(".count($residents).'|'.count($residents2).') posts:' . count($bot->forum->posts[$forum_id][$thread_id]['data']) . '|' . count($post));
                for($idx = count($post); $idx <= count($bot->forum->posts[$forum_id][$thread_id]['data']); $idx++) {
                  $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$idx]['post_id']);
                }
                if (is_array($bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id])) {
                  if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $bot->forum->posts[$forum_id][$thread_id]['data'][$_post_id]['post_id'], $post[$_post_id])) {
                    $bot->log("Military forum {$thread_name}/{$thread_id}/{$_post_id}: edit post error!");
                    $bot->debug($post[$_post_id]);
                    $error = 3;
                  }
                } else {
                  if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post[$_post_id])) {
                    $bot->log("Military forum {$thread_name}/{$thread_id}: create post error!");
                    $bot->debug($post[$_post_id]);
                    $error = 3;
                  }
                }
              }
            } else {
              $error = 4;
              $bot->log("Military forum {$thread_name}: error!");
              $redis->DEL("{$military_key}:{$alliance_key}:forum:{$continent_key}:id");
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
    $bot->log("Military error: no forum '" . BOT_STATISTICS_FORUM . "'");
    $redis->DEL("{$military_key}:{$alliance_key}:forum:id");
  }
}, 'statistic');

// callbacks
$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Stats",                 // command key
                       "LouBot_statistik",      // callback function
                       true,                    // is a command PRE needet?
                       '/^(stat|stats|statistik)$/i',// optional regex for key
function ($bot, $data) {
  if ($bot->is_ally_user($data['user'])) {
    if (!empty($data['params'][0])) {
      $nick = $bot->get_nick($data['params'][0]);
      if ($nick) $message = "[url]".STATS_URL."/spieler.php?name={$nick}[/url]";
      else $message = "[i]{$data['params'][0]}[/i] kenn ich nicht!";
    }
    else $message = "[url]".STATS_URL."/spieler.php?name={$data['user']}[/url]";
    if ($data["channel"] == ALLYIN) {
      $bot->add_allymsg($message);
    } else {
      $bot->add_privmsg($message, $data['user']);
    }
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'statistic');
?>