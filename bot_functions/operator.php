<?php
global $bot;
$bot->add_category('operator', array(), OPERATOR);
// crons

// callbacks
$bot->add_privmsg_hook("ReloadHooks",           // command key
                       "LouBot_reload_hooks",   // callback function
                       true,                    // is a command PRE needet?
                       '',                      // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    if ($bot->reload()) $bot->add_privmsg("Funktionen neu geladen!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("Say",                  // command key
                       "LouBot_say",           // callback function
                       true,                   // is a command PRE needet?
                       '/^say$/i',             // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    $bot->add_allymsg(implode(' ', $data['params']));
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("Ignore",              // command key
                       "LouBot_ignore",       // callback function
                       true,                  // is a command PRE needet?
                       '/^ignore$/i',         // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    if (empty($data['params'][0]) || !$bot->get_user_id($data['params'][0])) return true;
    if($bot->lou->set_ignore($data['params'][0])) $bot->add_privmsg("Done!", $data['user']);
    else $bot->add_privmsg("Error!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("UnIgnore",              // command key
                       "LouBot_unignore",       // callback function
                       true,                    // is a command PRE needet?
                       '/^unignore$/i',         // optional regex for key
function ($bot, $data) {
  global $redis;
  if($bot->is_op_user($data['user'])) {
    if (empty($data['params'][0]) || !($nick = $bot->get_nick($data['params'][0]))) return true;
    $ignore_key = "ignore";
    $alliance_key = "alliance:{$bot->ally_id}";  
    if (!($ignoreId = $redis->hGet("{$ignore_key}:{$alliance_key}", $nick))) return $bot->add_privmsg("Not listed!", $data['user']);;
    if($bot->lou->del_ignore($ignoreId)) {
      if ($redis->hDel("{$ignore_key}:{$alliance_key}", $nick))
        $bot->add_privmsg("Done!", $data['user']);
      else $bot->add_privmsg("Error!", $data['user']);
    }
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("ResetForen",              // command key
                       "LouBot_reset_ally_forum", // callback function
                       true,                      // is a command PRE needet?
                       '',                        // optional regex for key
function ($bot, $data) {
  global $redis;
  if($bot->is_op_user($data['user'])) {
    //foren
    $foren = array('settler', 'black', 'military', 'doku');
    $alliance_key = "alliance:{$bot->ally_id}";
    foreach($foren as $forum) {
      $redis->DEL("{$forum}:{$alliance_key}:forum:id");
      $keys = $redis->getKeys("{$forum}:{$alliance_key}:forum:continent:*:id");
      if (is_array($keys)) foreach($keys as $key) {
        $redis->DEL("{$key}");
      }
    }
    $bot->add_privmsg("Done!", $data['user']);
  }
}, 'operator');

$bot->add_privmsg_hook("Whisper",             // command key
                       "LouBot_whisper",      // callback function
                       true,                  // is a command PRE needet?
                       '/^(whisper|privat)$/i',            // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    $user = array_shift($data['params']);
    $bot->add_privmsg((implode(' ', $data['params']), $user);
    
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("Offizier",         // command key
                       "LouBot_happy_offi",// callback function
                       true,               // is a command PRE needet?
                       '/^(offizier|offi|nadel)$/i',      // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $nick = ucfirst(strtolower($bot->get_random_nick($nick)));
    $bot->add_allymsg("0===[}::::::::::::::> {$nick} <::::::::::::::0{]===0");
} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("Ping",                  // command key
                       "LouBot_ping_pong",      // callback function
                       false,                   // is a command PRE needet?
                       '/^[!]?(Ping|Pong)$/',        // optional regex for key
function ($bot, $data) {
  if($bot->is_himself($data['user'])) {
    if ($data['message'] == 'Ping') $bot->add_privmsg("Pong", $bot->bot_user_name);
    else $bot->add_privmsg("Pong", $data['user']);
  } else if ($bot->is_op_user($data['user']) && $data['command'][0] == PRE) {
    $bot->add_privmsg("Ping", $bot->bot_user_name);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("OC",                  // command key
                       "LouBot_ochat",        // callback function
                       true,                  // is a command PRE needet?
                       '',                    // optional regex for key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    $bot->add_globlmsg(implode(' ', $data['params']));
    
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("ReloadPhrases",         // command key
                       "LouBot_reload_phrases", // callback function
                       true,                    // is a command PRE needet?
                       '',                      // optional regex for key
function ($bot, $data) {
  global $phrases;
  if($bot->is_op_user($data['user'])) {
    $phrases = array();
    $bot->add_privmsg("Zitate neu geladen!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("RedisTest",             // command key
                       "LouBot_redis_test",     // callback function
                       true,                    // is a command PRE needet?
                       '',                      // optional regex for key
function ($bot, $data) {
  global $redis;
  if($bot->is_op_user($data['user'])) {
    $redis->setnx('operator:test', 0);
    $a = $redis->get('operator:test');
    $b = $redis->incr('operator:test');
    $bot->add_privmsg("Redis test return: $b>$a", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_tick_event(Cron::TICK15,                         // Cron key
                    "DoRedisTest",                         // command key
                    "LouBot_redis_test_cron",              // callback function
function ($bot, $data) {
  global $redis;
  $error = false;
  if (!$redis->status()) $error = true;
  else {
    $a = $redis->Set('operator:test', 1);
    $b = $redis->get('operator:test');
    $c = $redis->incr('operator:test');
    $d = $redis->get('operator:test');
    if (!$a || $b != 1 || $c != 2 || $c != $d) $error = true;
  } 
  if (!$error) $bot->log("Redis test: ok!");
  else $bot->log("Redis test: Error!");
}, 'operator');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                   "SetUserAlias",          // command key
                   "LouBot_set_user_alias", // callback function
                   true,                    // is a command PRE needet?
                   '',                      // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if ($bot->is_op_user($data['user'])) {
    if ($bot->is_ally_user($data['params'][0])) {
      if (!$bot->is_ally_user($data['params'][1])) {
        if (!preg_match('/^[a-zA-Z]{1}[a-zA-Z0-9-_.]{1,15}$/', $data['params'][1])) {
          $message = 'Der Alias [i]' . mb_strtolower($data['params'][0]) . '[/i] ist ungültig!';
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg($message);
          else 
            $bot->add_privmsg($message, $data['user']);
          return true;
        }
        $uid = $bot->get_user_id($data['params'][0]);
        $alias = mb_strtoupper($data['params'][1]);
        $insert = $redis->HMSET("aliase", array(
          $alias => $uid
        ));
        if ($insert) {
          $redis->SADD("user:{$uid}:alias", $alias);
          $_alias = ucfirst(mb_strtolower($alias));
          $message = ucfirst($data['params'][0])."s Alias [i]{$_alias}[/i] gesetzt.";
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg($message);
          else 
            $bot->add_privmsg($message, $data['user']);        
          return true;
        }
      } else {
        $uid = $bot->get_user_id($data['params'][1]);
        $nick = $redis->HGET("user:{$uid}:data", 'name');
        $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][1])) . "[/i] ist von {$nick} belegt!";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
        return true;
      }
    } else {
      $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist nicht belegt!";
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg($message);
      else 
        $bot->add_privmsg($message, $data['user']);
      return true;
    }
    $bot->add_privmsg('Alias Fehler: falsche Parameter!', $data['user']);

  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                   "DelUserAlias",            // command key
                   "LouBot_del_user_alias",   // callback function
                   true,                      // is a command PRE needet?
                   '',                        // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if ($bot->is_op_user($data['user'])) {
    if ($bot->is_ally_user($data['params'][0])) {  
      if ($bot->is_ally_user($data['params'][1])) {
        $uid = $bot->get_user_id($data['params'][1]);
        $nick = $redis->HGET("user:{$uid}:data", 'name');
        if (mb_strtoupper($data['params'][0]) == mb_strtoupper($data['params'][1])) {
          $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] kann nicht gelöscht werden!";
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg($message);
          else 
            $bot->add_privmsg($message, $data['user']);
        } else if ($nick != $data['params'][0]) {
          $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist von {$nick} belegt!";
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg($message);
          else 
            $bot->add_privmsg($message, $data['user']);
        } else {
          $alias = mb_strtoupper($data['params'][1]);
          $redis->HDEL("aliase", $alias);
          $redis->SREM("user:{$uid}:alias", $alias);
          $message = ucfirst($data['params'][0]).' Alias [i]' . ucfirst(mb_strtolower($data['params'][1])) . "[/i] ist gelöscht!";
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg($message);
          else 
            $bot->add_privmsg($message, $data['user']);
        }
        return true;
      } else {
        $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][1])) . "[/i] ist nicht belegt!";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
        return true;
      }
    } else {
      $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist nicht belegt!";
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg($message);
      else 
        $bot->add_privmsg($message, $data['user']);
      return true;
    }
    $bot->add_privmsg('Alias Fehler: falsche Parameter!', $data['user']);

  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');
?>