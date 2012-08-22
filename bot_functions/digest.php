<?php
global $bot;
$bot->add_category('digest', array(), PUBLICY);
// crons
/*
$bot->add_cron_event(Cron::DAILY,                           // Cron key
                    "GenerateDailyDigest",                  // command key
                    "LouBot_generate_daily_digest_cron",    // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $continents = $redis->SMEMBERS("continents");
  $alliance_key = "alliance:{$bot->ally_id}";
  $digest_key = "digest";
  $settler_key = "settler";
  if (is_array($continents)) foreach ($continents as $continent) {
    $continent_key = "continent:{$continent}";
    $receivers = $redis->SDIFF("{$settler_key}:{$alliance_key}:{$continent_key}:residents", "{$digest_key}:{$alliance_key}:nomail");
    // can check rights with $access = $bot->get_access($data['user'], $allow);
    $bot->log('Digest: found '.count($receivers).' receivers for K'.$continent);
    send_digest($continent, $receivers);
  }
}, 'digest');
*/

// callbacks
$bot->add_msg_hook(array(PRIVATEIN),
                   "digest",               // command key
                   "LouBot_digest",        // callback function
                   true,                   // is a command PRE needet?
                   '/^digest$/i',          // optional regex for key
function ($bot, $data) {
  global $redis, $sms;
  if (!$redis->status()) return;
  $commands = array('off', 'on', 'mail');
  $continents = $redis->SMEMBERS("continents");
  $alliance_key = "alliance:{$bot->ally_id}";
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
      if (in_array(strtolower($data['params'][0]), $commands) || (stripos($data['params'][0], 'k') !== false && in_array(substr($data['params'][0], 1), $continents))) {
      $second_argument = strtolower(Lou::prepare_chat($data['params'][1]));
      $digest_key = "digest";
      switch (strtolower($data['params'][0])) {
        case 'off':
          if($redis->SADD("{$digest_key}:{$alliance_key}:nomail", $data['user'])) {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist aus dem Mailverteiler abgemeldet!";
            else $message = 'Du bist aus dem Mailverteiler abgemeldet!';
          } else if($redis->SISMEMBER("{$digest_key}:{$alliance_key}:nomail", $data['user'])) {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist aus dem Mailverteiler abgemeldet!";
            else $message = 'Du bist aus dem Mailverteiler abgemeldet!';
          }
          break;
        case 'on':
          if($redis->SREM("{$digest_key}:{$alliance_key}:nomail", $data['user'])) {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist im Mailverteiler angemeldet!";
            else $message = 'Du bist im Mailverteiler angemeldet!';
          } else if(!$redis->SISMEMBER("{$digest_key}:{$alliance_key}:nomail", $data['user'])) {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist im Mailverteiler angemeldet!";
            else $message = 'Du bist im Mailverteiler angemeldet!';
          }
          break;
        case 'mail':
          if($redis->SISMEMBER("{$digest_key}:{$alliance_key}:nomail", $data['user'])) {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist aus dem Mailverteiler abgemeldet!";
            else $message = 'Du bist aus dem Mailverteiler abgemeldet!';
          } else {
            if ($data["channel"] == ALLYIN) $message = "{$data['user']}, du bist im Mailverteiler angemeldet!";
            else $message = 'Du bist im Mailverteiler angemeldet!';
          }
          break;
        case (stripos($data['params'][0], 'k') !== false && in_array(substr($data['params'][0], 1), $continents)):
          if ($bot->is_op_user($data['user'])) {
            $continent = substr($data['params'][0], 1);
            $hours = array(1,2,6,12,24,36,72);
            $range = (in_array(intval($data['params'][1]), $hours)) ? intval($data['params'][1]) : 2;
            $message = "Du bekommst den aktuellen Digest-{$range}h für K{$continent} zugestellt!";
            send_digest_3($continent, $data['user'], mktime((date("H") - $range), 0, 0, date("n"), date("j"), date("Y")), $range);
            break;
          } else $message = "Ne Ne Ne!";
      }
    } else $bot->add_privmsg('Digest: falsche Parameter ('.Lou::prepare_chat($data['params'][0]).')!', $data['user']);

    if ($data["channel"] == ALLYIN)
      $bot->add_allymsg($data['user'] . ', ' . $message);
    else 
      $bot->add_privmsg($message, $data['user']);
    return true;
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'digest');

if(!function_exists('send_digest_3')) {
  function send_digest_3($continents, $receivers, $start = false, $range = 24) {
    global $bot, $redis;
    if(!is_array($continents)) $continents = array($continents);
    if(!is_array($receivers)) $receivers = array($receivers);
    $digest_key = "digest";
    $digest = array();
    $_start = ($start) ? $start : mktime(date("H"), 0, 0, date("n"), (date("j") - 1), date("Y"));
    $_end = mktime(date("H"), 0, 0, date("n"), date("j"), date("Y"));
    foreach($continents as $continent) {
      $digest[] = "[u]Digest der letzten {$range} Std. für K{$continent}[/u]\n\n";
      $continent_key = "continent:{$continent}";
      $bot->log('Digest: send '.count($receivers).' messages to K'.$continent);
      $keys = $redis->clearKey($redis->getKeys("{$digest_key}:{$continent_key}:*"), "/{$digest_key}:{$continent_key}:/");
      if(is_array($keys)) foreach($keys as $key) {
        $_digs = array_flip($redis->ZRANGEBYSCORE("{$digest_key}:{$continent_key}:{$key}", "{$_start}", "{$_end}", array('withscores' => TRUE)));
        switch($key) {
          case 'cities:overtake':
            $$key = "[b]Städteübernahmen:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
              foreach($obj as $k => $v) {
                $city_id = $redis->HGET("cities", $k);
                $city_data = $redis->HGETALL("city:{$city_id}:data");
                $user_new = $redis->HGETALL("user:{$v[0]}:data");
                $ally_new_name = $redis->HGET("alliance:{$user_new['alliance']}:data", 'name');
                $user_old = $redis->HGETALL("user:{$v[1]}:data");
                $ally_old_name = $redis->HGET("alliance:{$user_old['alliance']}:data", 'name');
                $icon = ($bot->ally_id == $user_new['alliance'] || $bot->ally_id == $user_old['alliance']) ? '∗ ' : '  ';
                $$key .= "{$icon}[i]{$city_data['category']}[/i] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}) - [i]{$city_data['name']}[/i] - " . (($user_old['name']) ?  "[s][spieler]{$user_old['name']}[/spieler][/s]" . (($ally_old_name) ? "[s][[allianz]{$ally_old_name}[/allianz]][/s]":"") : '[i]Lawless[/i]');
                $$key .= " ⇒ wurde von [spieler]{$user_new['name']}[/spieler]" . (($ally_new_name) ? "[[allianz]{$ally_new_name}[/allianz]]":"") . " übernommen";
                $$key .= "\n";
              }
             } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'cities:new':
            $$key = "[b]neue Städte:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
              foreach($obj as $k) {
                $city_id = $redis->HGET("cities", $k);
                $city_data = $redis->HGETALL("city:{$city_id}:data");
                $user_new = $redis->HGETALL("user:{$city_data['user_id']}:data");
                $ally_new_name = $redis->HGET("alliance:{$city_data['alliance_id']}:data", 'name');
                $icon = ($bot->ally_id == $city_data['alliance_id']) ? '∗ ' : '  ';
                $$key .= "{$icon}[i]{$city_data['category']}[/i] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}) - [i]{$city_data['name']}[/i] - " . (($user_new['name']) ?  "[spieler]{$user_new['name']}[/spieler]" . (($ally_new_name) ? "[[allianz]{$ally_new_name}[/allianz]]":"") : '[i]Lawless[/i]');
                $$key .= "\n";
              }
             } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'cities:palace':
            $$key = "[b]neue Palaste:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
              foreach($obj as $k => $v) {
                $city_id = $redis->HGET("cities", $k);
                $city_data = $redis->HGETALL("city:{$city_id}:data");
                $user_new = $redis->HGETALL("user:{$city_data['user_id']}:data");
                $ally_new_name = $redis->HGET("alliance:{$city_data['alliance_id']}:data", 'name');
                $icon = ($bot->ally_id == $city_data['alliance_id']) ? '∗ ' : '  ';
                $$key .= "{$icon}[i]{$city_data['category']}[/i] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}) - [i]{$city_data['name']}[/i] - " . (($user_new['name']) ?  "[spieler]{$user_new['name']}[/spieler]" . (($ally_new_name) ? "[[allianz]{$ally_new_name}[/allianz]]":"") : '[i]Lawless[/i]');
                $$key .= "\n";
              }
             } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'cities:castles':
            $$key = "[b]neue Burgen:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
              foreach($obj as $k => $v) {
                $city_id = $redis->HGET("cities", $k);
                $city_data = $redis->HGETALL("city:{$city_id}:data");
                $user_new = $redis->HGETALL("user:{$city_data['user_id']}:data");
                $ally_new_name = $redis->HGET("alliance:{$city_data['alliance_id']}:data", 'name');
                $icon = ($bot->ally_id == $city_data['alliance_id']) ? '∗ ' : '  ';
                $$key .= "{$icon}[i]{$city_data['category']}[/i] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}) - [i]{$city_data['name']}[/i] - " . (($user_new['name']) ?  "[spieler]{$user_new['name']}[/spieler]" . (($ally_new_name) ? "[[allianz]{$ally_new_name}[/allianz]]":"") : '[i]Lawless[/i]');
                $$key .= "\n";
              }
             } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'cities:lawless':
            $$key = "[b]neue Lawless:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
                foreach($obj as $k) {
                  $city_id = $redis->HGET("cities", $k);
                  $city_data = $redis->HGETALL("city:{$city_id}:data");
                  $user_old = $redis->HGETALL("user:{$city_data['ll_user_id']}:data");
                  $ally_old_name = $redis->HGET("alliance:{$city_data['ll_alliance_id']}:data", 'name');
                  $icon = ($bot->ally_id == $city_data['ll_alliance_id']) ? '∗ ' : '  ';
                  $$key .= "{$icon}[i]{$city_data['category']}[/i] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}/{$city_data['ll_points']}) - [i]{$city_data['ll_name']}[/i] - [spieler]{$user_old['name']}[/spieler]" . (($ally_old_name) ? "[[allianz]{$ally_old_name}[/allianz]]":"");
                  $$key .= "\n";
                }
              } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'cities:rename':
            $$key = "[b]Städteumbenennung:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
                foreach($obj as $k => $v) {
                  $city_id = $redis->HGET("cities", $k);
                  $city_data = $redis->HGETALL("city:{$city_id}:data");
                  $user_new = $redis->HGETALL("user:{$city_data['user_id']}:data");
                  $ally_new_name = $redis->HGET("alliance:{$city_data['alliance_id']}:data", 'name');
                  $icon = ($bot->ally_id == $city_data['alliance_id']) ? '∗ ' : '  ';
                  $$key .= "{$icon}[i]{$city_data['category']}[/i] - [stadt]{$city_data['pos']}[/stadt] ({$city_data['points']}) - [s]{$v[1]}[/s] - wurde umbenannt in [i]{$v[0]}[/i] - " . (($user_new['name']) ?  "[spieler]{$user_new['name']}[/spieler]" . (($ally_new_name) ? "[[allianz]{$ally_new_name}[/allianz]]":"") : '[i]Lawless[/i]');
                  $$key .= "\n";
                }
              } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'aliances:new':
            $$key = "[b]neue Allianzen:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
                foreach($obj as $k) {
                  $alliance_id = $redis->HGET("alliances", $k);
                  $ally_data = $redis->HGETALL("alliance:{$alliance_id}:{$continent_key}:data");
                  $icon = ($bot->ally_id == $alliance_id) ? '∗ ' : '  ';
                  $$key .= "{$icon}[i][alliance]{$k}[/alliance][/i] - Punkte:{$ally_data['points']} Spieler:{$ally_data['members']} Städte:{$ally_data['cities']}";
                  $$key .= "\n";
                }
              } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'aliances:left':
            $$key = "[b]Allianzen die aufgegeben haben:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
                foreach($obj as $k) {
                  $$key .= "{$icon}[s][alliance]{$k}[/alliance][/s]";
                  $$key .= "\n";
                }
              } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'residents:new':
            $$key = "[b]neue Spieler:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
                foreach($obj as $k) {
                  $user_id = $redis->HGET("users", $k);
                  $user_new = $redis->HGETALL("user:{$user_id}:data");
                  $ally_new_name = $redis->HGET("alliance:{$user_new['alliance_id']}:data", 'name');
                  $$key .= "{$icon}[i][spieler]{$k}[/spieler][/i]" . (($ally_new_name) ? "[[allianz]{$ally_new_name}[/allianz]]":"");
                  $$key .= "\n";
                }
              } $digest[] = $$key;
            } else unset($$key);
            break;
          case 'residents:left':
            $$key = "[b]Spieler die aufgegeben haben:[/b]\n";
            if(!empty($_digs)) { foreach($_digs as $_time => $_dig) {
              $obj = json_decode($_dig);
                foreach($obj as $k) {
                  $user_id = $redis->HGET("users", $k);
                  $user_old = $redis->HGETALL("user:{$user_id}:data");
                  $ally_old_name = $redis->HGET("alliance:{$user_old['alliance_id']}:data", 'name');
                  $icon = ($bot->ally_id == $user_old['alliance_id']) ? '∗ ' : '  ';
                  $$key .= "{$icon}[s][spieler]{$k}[/spieler][/s]" . (($ally_old_name) ? "[[allianz]{$ally_old_name}[/allianz]]":"");
                  $$key .= "\n";
                }
              } $digest[] = $$key;
            } else unset($$key);
            break;
        }
      }
      $digest[] .= "";
    }
    $bot->igm->send(implode(';',$receivers), "♲ Digest-{$range}h für K" . implode(', K', $continents), implode('', $digest));
  }
}
?>