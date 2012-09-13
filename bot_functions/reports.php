<?php
global $bot;
$bot->add_category('reports', array(), PUBLICY);
// crons
$bot->add_thread_event(Cron::TICK10,                     // Cron key
                    "GetAllianceReportUpdate",           // command key
                    "LouBot_report_city_update_thread",  // callback function
function ($_this, $bot, $data) {
  $redis = RedisWrapper::getInstance();
  if (!$redis->status()) return;
  $alliance_key = "alliance:{$bot->ally_id}";
  $redis->SADD("stats:{$alliance_key}:AllianceReportUpdate", (string)time());
  $members = $redis->SMEMBERS("{$alliance_key}:member");
  if (is_array($members)) foreach ($members as $member) {
    $_this->setAlive();
    $user_id = $redis->hget("users", $member);
    $cities = $redis->SMEMBERS("user:{$user_id}:cities");
    if (is_array($cities)) foreach ($cities as $city) {
      $city_id = $redis->hget("cities", $city);
      //only castles
      //if ($redis->hget("city:{$city_id}:data", 'state') >= 1) 
        $count = $redis->ZCARD("city:{$city_id}:{$alliance_key}:reports");
      $bot->lou->get_city_reports($city_id, $count);
    }    
  }
  $bot->debug("Stopped " . posix_getpid() . '@'. $_this->getName());
});

$bot->add_tick_event(Cron::TICK5,                // Cron key
                    "GetReportUpdate",           // command key
                    "LouBot_report_update_cron", // callback function
function ($bot, $data) {
  global $redis, $_GAMEDATA;
  if (!$redis->status()) return;
  $redis->SADD("stats:{$alliance_key}:ReportUpdate", (string)time());
  $continents = $redis->SMEMBERS("continents");
  $alliance_key = "alliance:{$bot->ally_id}";
  $reports_key = "reports";
  if (!($forum_id = $redis->GET("{$reports_key}:{$alliance_key}:forum:id"))) {
    $forum_id = $bot->forum->get_forum_id_by_name(BOT_REPORTS_FORUM, true);
    $redis->SET("{$reports_key}:{$alliance_key}:forum:id", $forum_id);
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
      $thread = new executeThread("{$reports_key}Thread-" . $c_id);
      $thread->worker = function($_this, $bot, $continents, $forum_id) {
        global $_GAMEDATA;
        // working child
        $error = 0;
        $redis = RedisWrapper::getInstance($_this->getPid());
        $last_update = $redis->SMEMBERS("stats:{$alliance_key}:AllianceReportUpdate");
        sort($last_update);
        $last_update = end($last_update);
        $alliance_key = "alliance:{$bot->ally_id}";
        $reports_key = "reports";
        $reports_chunks = 10;
        $max_reports = 10;
        $skip_friedly_fire = true;
        $str_time = (string)time();
        $bot->log("Fork: " . $_this->getName() .": start");
        foreach ($continents as $continent) {
          // ** continents
          if ($continent >= 0) {
            $bot->lou->check();
            $thread_name = 'K'.$continent;
            $bot->debug("Reports forum {$thread_name}: start");
            $continent_key = "continent:{$continent}";
            if (!($thread_id = $redis->GET("{$reports_key}:{$alliance_key}:forum:{$continent_key}:id"))) {
              $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
              $redis->SET("{$reports_key}:{$alliance_key}:forum:{$continent_key}:id", $thread_id);
            }
            $update = false;
            $reports_pattern = "{$alliance_key}:{$continent_key}:reports";
#            if ($thread_id) {
            if ($bot->forum->exist_forum_thread_id($forum_id, $thread_id)) {
              
              // ** daily
              $daily = array();
              $_start = mktime(0, 0, 0, date("n"), date("j"), date("Y"));
              $_reports = $redis->ZRANGEBYSCORE("{$reports_pattern}", "{$_start}", "+inf");
              if(is_array($_reports)) foreach($_reports as $_report) {
                $_header = $redis->HGETALL("{$alliance_key}:reports:{$_report}:header");
                if ($_header['report_type'] != $_GAMEDATA->translations['tnf:city']) continue;
                if ($skip_friedly_fire && $_header['opponent_alliance'] == $bot->ally_id) continue;
                $update = ($redis->SADD("{$reports_key}:{$alliance_key}:reports", $_report)) ? true : $update;
                $_opponent_alliance = ($_header['opponent_alliance'] >= 1) ? '[[allianz]' . $redis->hget("alliance:{$_header['opponent_alliance']}:data", 'name') . '[/allianz]]' : '';
                $_forum_text = str_replace($_header['opponent_name'], "[spieler]{$_header['opponent_name']}[/spieler]" . $_opponent_alliance  , $_header['report_text']);
                $_forum_text = str_replace("{$_header['name']}:", ''  , $_forum_text);
                /* DE
                Überfall 
                Ausspioniert
                Unterstützung
                Belagerung 
                Plünderung
                */
                if (strstr($_forum_text, 'Überfall')) $_img = '♗';
                elseif (strstr($_forum_text, 'Ausspioniert')) $_img = '♘';
                elseif (strstr($_forum_text, 'Unterstützung')) $_img = '♖';
                elseif (strstr($_forum_text, 'Belagerung')) $_img = '♚';
                elseif (strstr($_forum_text, 'Plünderung')) $_img = '♙';
                else $_img = '♯';
                $daily[$_header['pos']]['head'] = "
[u]{$_header['category']} [b][stadt]{$_header['pos']}[/stadt][/b][/u] ~ [i]{$_header['name']}[/i] - [spieler]{$_header['owner_name']}[/spieler]";
                $daily[$_header['pos']]['entrys'][] = " {$_img} " . date('H:i:s', $_header['time']) . " - [i]{$_forum_text}[/i]
 ⇒ [report]{$_header['report_link']}[/report]";
              }
// post txt
$post_daily_head = "[b][u]heutige Berichte auf dem Kontinent:[/u] {$thread_name}[/b]

";
$post_daily_footer = "

[u]letztes Update:[/u] [i]" . date('d.m.Y H:i:s', $str_time) . "[/i] | [u]Datenbank:[/u] [i]" . date('d.m.Y H:i:s', $last_update) . "[/i]" . (($update) ? ' | ('.count($new_reports).')':'');
              $chunks = array();
              $post_daily = array();
              $dailys = array();
              if (!empty($daily)) {
                foreach($daily as $city) {
                  $dailys[] = $city['head'];
                  $entrys = array_reverse($city['entrys']);
                  foreach(array_slice($entrys, 0, $max_reports) as $entry) {
                    $dailys[] = $entry;
                  }
                  if (count($entrys) >= $max_reports) $dailys[] = PHP_EOL . "(max. {$max_reports} pro Stadt)";
                }

                $chunks = array_chunk($dailys, $reports_chunks);
                if(is_array($chunks)) foreach($chunks as $page => $item) {
                  $post_daily[$page] = ($page == 0) ? $post_daily_head : "";
                  $post_daily[$page] .= trim(implode("
", $item));
                  $post_daily[$page] .= "
";
                  $post_daily[$page] .= ($page == (count($chunks)-1)) ? $post_daily_footer : "";
                }
              } else $post_daily[] = $post_daily_head . "[i]keine Berichte[/i]" . $post_daily_footer;

              // ** forum
              // ** forum
              $post = array();
              $_post_id = 0;
              
              foreach($post_daily as $_post_daily) {
                $post[$_post_id ++] = $_post_daily;
              }
              // ** weekly
              $weekly = array();
              $_start = mktime(0, 0, 0, date("n"), date("j")-7, date("Y"));
              $_end = mktime(0, 0, 0, date("n"), date("j"), date("Y"));
              $_reports = $redis->ZRANGEBYSCORE("{$reports_pattern}", "{$_start}", "({$_end}");
              if(is_array($_reports)) foreach($_reports as $_report) {
                $_header = $redis->HGETALL("{$alliance_key}:reports:{$_report}:header");
                if ($_header['report_type'] != $_GAMEDATA->translations['tnf:city']) continue;
                if ($skip_friedly_fire && $_header['opponent_alliance'] == $bot->ally_id) continue;
                $update = ($redis->SADD("{$reports_key}:{$alliance_key}:reports", $_report)) ? true : $update;
                $_opponent_alliance = ($_header['opponent_alliance'] >= 1) ? '[[allianz]' . $redis->hget("alliance:{$_header['opponent_alliance']}:data", 'name') . '[/allianz]]' : '';
                $_forum_text = str_replace($_header['opponent_name'], "[spieler]{$_header['opponent_name']}[/spieler]" . $_opponent_alliance  , $_header['report_text']);
                $_forum_text = str_replace("{$_header['name']}:", ''  , $_forum_text);
                /* DE
                Überfall 
                Ausspioniert
                Unterstützung
                Belagerung 
                Plünderung
                */
                if (strstr($_forum_text, 'Überfall')) $_img = '♗';
                elseif (strstr($_forum_text, 'Ausspioniert')) $_img = '♘';
                elseif (strstr($_forum_text, 'Unterstützung')) $_img = '♖';
                elseif (strstr($_forum_text, 'Belagerung')) $_img = '♚';
                elseif (strstr($_forum_text, 'Plünderung')) $_img = '♙';
                else $_img = '♯';
                $weekly[$_header['pos']]['head'] = "
[u]{$_header['category']} [b][stadt]{$_header['pos']}[/stadt][/b][/u] ~ [i]{$_header['name']}[/i] - [spieler]{$_header['owner_name']}[/spieler]";
                $weekly[$_header['pos']]['entrys'][] = " {$_img} " . date('d.m.Y H:i:s', $_header['time']) . " - [i]{$_forum_text}[/i]
 ⇒ [report]{$_header['report_link']}[/report]";
              }
// post txt
$post_weekly_head = "[b][u]Berichte der letzten 7 Tage auf dem Kontinent:[/u] {$thread_name}[/b]

";
$post_weekly_footer = '

';
              $chunks = array();
              $post_weekly = array();
              $weeklys = array();
              if (!empty($weekly)) {
                foreach($weekly as $city) {
                  $weeklys[] = $city['head'];
                  $entrys = array_reverse($city['entrys']);
                  foreach(array_slice($entrys, 0, $max_reports) as $entry) {
                    $weeklys[] = $entry;
                  }
                  if (count($entrys) >= $max_reports) $weeklys[] = PHP_EOL . "(max. {$max_reports} pro Stadt)";
                }

                $chunks = array_chunk($weeklys, $reports_chunks);
                if(is_array($chunks)) foreach($chunks as $page => $item) {
                  $post_weekly[$page] = ($page == 0) ? $post_weekly_head : "";
                  $post_weekly[$page] .= trim(implode("
", $item));
                  $post_weekly[$page] .= "
";
                  $post_weekly[$page] .= ($page == (count($chunks)-1)) ? $post_weekly_footer : "";
                }
              } else $post_weekly[] = $post_weekly_head . "[i]keine Berichte[/i]" . $post_weekly_footer;

              // ** forum
              foreach($post_weekly as $_post_weekly) {
                $post[$_post_id ++] = $_post_weekly;
              }
              // new last post = update
// post txt
$post_update = "[u]Legende[/u]:
     ♗ - [i]Überfall[/i]
     ♘ - [i]Ausspioniert[/i]
     ♖ - [i]Unterstützung[/i]
     ♚ - [i]Belagerung[/i]
     ♙ - [i]Plünderung[/i]
";
          
              // ** forum            
              foreach ($post as $_post_id_post => $_post) {
                if ($_id = $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $_post_id_post)) {
                  if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $_id, $_post)) {
                    $bot->log("Reports forum {$thread_name}/{$thread_id}/{$_post_id_post}: edit post error!");
                    $bot->debug($_post);
                    $error = 3;
                  }
                } else {
                  if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $_post)) {
                    $bot->log("Reports forum {$thread_name}/{$thread_id}: create post error!");
                    $bot->debug($_post);
                    $error = 3;
                  }
                }
              }
              $_posts_count = $bot->forum->get_thread_post_count($forum_id, $thread_id);
              if ($update && $_posts_count >= count($post)) {
                $bot->log("Reports forum {$thread_name}: update(".count($new_reports).') posts:' . $_posts_count . '|' . count($post));
                for($idx = count($post); $idx <= $_posts_count; $idx++) {
                  $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $idx));
                }
                if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post_update)) {
                  $bot->log("Reports forum {$thread_name}/{$thread_id}: create post error!");
                  $bot->debug($post_update);
                  $error = 3;
                }
              } else {
                $post[$_post_id] = $post_update;
                $bot->log("Reports forum {$thread_name}: info(".count($new_reports).') posts:' . $_posts_count . '|' . count($post));
                for($idx = count($post); $idx <= $_posts_count; $idx++) {
                  $bot->forum->delete_alliance_forum_threads_post($forum_id, $thread_id, $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $idx));
                }
                if ($_id = $bot->forum->get_thread_post_id_by_num($forum_id, $thread_id, $_post_id)) {
                  if (!$bot->forum->edit_alliance_forum_post($forum_id, $thread_id, $_id, $post[$_post_id])) {
                    $bot->log("Reports forum {$thread_name}/{$thread_id}/{$_post_id}: edit post error!");
                    $bot->debug($post[$_post_id]);
                    $error = 3;
                  }
                } else {
                  if (!$bot->forum->create_alliance_forum_post($forum_id, $thread_id, $post[$_post_id])) {
                    $bot->log("Reports forum {$thread_name}/{$thread_id}: create post error!");
                    $bot->debug($post[$_post_id]);
                    $error = 3;
                  }
                }
              }
            } else {
              $error = 4;
              $bot->log("Reports forum {$thread_name}: error!");
              $redis->DEL("{$reports_key}:{$alliance_key}:forum:{$continent_key}:id");
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
    $bot->log("Reports error: no forum '" . BOT_REPORTS_FORUM . "'");
    $redis->DEL("{$reports_key}:{$alliance_key}:forum:id");
  }
}, 'reports');

// hooks
$bot->add_privmsg_hook("RebaseReportsForum",          // command key
                       "LouBot_rebase_reports_forum", // callback function
                       true,                          // is a command PRE needet?
                       '',                            // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if($bot->is_op_user($data['user'])) {
    $continents = $redis->SMEMBERS("continents");
    $alliance_key = "alliance:{$bot->ally_id}";
    $reports_key = "reports";
    
    if (!($forum_id = $redis->GET("{$reports_key}:{$alliance_key}:forum:id"))) {
      $forum_id = $bot->forum->get_forum_id_by_name(BOT_REPORTS_FORUM, true);
    } else $redis->DEL("{$reports_key}:{$alliance_key}:forum:id");
    sort($continents);
    if (is_array($continents) && $bot->forum->exist_forum_id($forum_id)) {
      foreach ($continents as $continent) {
        // ** continents
        if ($continent >= 0) {
          $thread_name = 'K'.$continent;
          $bot->debug("Reports forum {$thread_name}: delete");
          $continent_key = "continent:{$continent}";
          if (!($thread_id = $redis->GET("{$reports_key}:{$alliance_key}:forum:{$continent_key}:id"))) {
            $thread_id = $bot->forum->get_forum_thread_id_by_title($forum_id, $thread_name, true);
            $redis->SET("{$reports_key}:{$alliance_key}:forum:{$continent_key}:id", $thread_id);
          } else $redis->DEL("{$reports_key}:{$alliance_key}:forum:{$continent_key}:id");
          $thread_ids[] = $thread_id;
        }
      }
      if ($bot->forum->delete_alliance_forum_threads($forum_id, $thread_ids)) {
        $bot->add_privmsg("Step1# ".BOT_REPORTS_FORUM." deleted!", $data['user']);
        $bot->call_event(array('type' => TICK, 'name' => Cron::TICK5), 'LouBot_report_update_cron');
        $bot->add_privmsg("Step2# ".BOT_REPORTS_FORUM." rebase done!", $data['user']);
      }
      else $bot->add_privmsg("Fehler beim löschen von: ".BOT_REPORTS_FORUM."", $data['user']);
    }
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("ReloadReportsForum",          // command key
                       "LouBot_reload_reports_forum", // callback function
                       true,                          // is a command PRE needet?
                       '',                            // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if($bot->is_op_user($data['user'])) {
    $alliance_key = "alliance:{$bot->ally_id}";
    $reports_key = "reports";
    $reports_key_keys = $redis->getKeys("{$reports_key}:{$alliance_key}:forum:*");
    if (!empty($reports_key_keys)) foreach($reports_key_keys as $reports_key_key) {
      $redis->DEL("{$reports_key_key}");
    }
    $bot->add_privmsg("Step1# ".BOT_REPORTS_FORUM." REDIS ids deleted!", $data['user']);
    $bot->call_event(array('type' => TICK, 'name' => Cron::TICK5), 'LouBot_report_update_cron');
    $bot->add_privmsg("Step2# ".BOT_REPORTS_FORUM." reload done!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

// test get report
$bot->add_privmsg_hook("ReportTest",          // command key
                       "LouBot_report_test",  // callback function
                       true,                  // is a command PRE needet?
                       '',                    // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    $bot->lou->get_report($data['params'][0]);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'reports');

$bot->add_reportheader_hook("UpdateReportHeader",                        // command key
                            "LouBot_alliance_report_header_update",      // callback function
function ($bot, $reports) {
  global $redis, $_GAMEDATA;
  if (is_array($reports) && $reports['type'] == REPORTHEADER) {
    $bot->lou->check();
    $city_key = "city:{$reports['id']}";
    $continent = $redis->HGET("{$city_key}:data", 'continent');
    $continent_key = "continent:{$continent}";
    $pos = $redis->HGET("{$city_key}:data", 'pos');
    $category = $redis->HGET("{$city_key}:data", 'category');
    $state = $redis->HGET("{$city_key}:data", 'state');
    $alliance_key = "alliance:{$bot->ally_id}";
    foreach($reports['data'] as $report) {
      $report_link = $bot->lou->get_report_link($report['id']);
      $report_key = "reports:{$report['id']}";
      $opponent_id = $bot->get_user_id($report['opponent']);
      $opp_alliance_id = $redis->HGET("user:{$opponent_id}:data", 'alliance');
      $redis->ZADD("{$city_key}:{$alliance_key}:reports", $report['time'], $report['id']);
      $redis->ZADD("{$alliance_key}:{$continent_key}:reports", $report['time'], $report['id']);
      $redis->HMSET("{$alliance_key}:{$report_key}:header", array(
              'time'              => $report['time'],
              'report_id'         => $report['id'],
              'id'                => $reports['id'],
              'name'              => $report['name'],
              'pos'               => $pos,
              'category'          => $category,
              'state'             => $state,
              'continent'         => $continent,
              'owner'             => $report['owner'],
              'owner_name'        => $report['owner_name'],
              'opponent'          => $opponent_id,
              'opponent_name'     => $report['opponent'],
              'opponent_alliance' => $opp_alliance_id,
              'report_type'       => $report['report_type'],
              'report_text'       => $report['report_text'],
              'report_link'       => $report_link
            ));
    }
  }
}, 'reports');
/*
$bot->add_report_hook("UpdateReport",                       // command key
                      "LouBot_alliance_report_update",      // callback function
function ($bot, $report) {
  global $redis, $_GAMEDATA;
  if (is_array($report) && $report['type'] == REPORT) {
    $alliance_key = "alliance:{$bot->ally_id}";
    $report_key = "reports:{$report['id']}";
    //$redis->SET("{$alliance_key}:{$report_key}:data", serialize($report['data']));
  }
}, 'reports');
*/
?>