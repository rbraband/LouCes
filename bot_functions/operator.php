<?php
global $bot;
$bot->add_category('operator', array(), OPERATOR);
$bot->add_privmsg_hook("ReloadHooks",           // command key
                       "LouBot_reload_hooks",   // callback function
                       true,                    // is a command PRE needet?
                       '',  										// optional regex für key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    if ($bot->reload()) $bot->add_privmsg("Funktionen neu geladen!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("Say",                	// command key
                       "LouBot_say",         	// callback function
                       true,                  // is a command PRE needet?
                       '',	  								// optional regex für key
function ($bot, $data) {
  if($bot->is_op_user($data['user'])) {
    $bot->add_allymsg(implode(' ', $data['params']));
    
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("ReloadPhrases",         // command key
                       "LouBot_reload_phrases", // callback function
                       true,                 		// is a command PRE needet?
                       '', 	                    // optional regex für key
function ($bot, $data) {
  global $phrases;
  if($bot->is_op_user($data['user'])) {
    $phrases = array();
    $bot->add_privmsg("Zitate neu geladen!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_privmsg_hook("RedisTest",         		// command key
                       "LouBot_redis_test", 		// callback function
                       true,                 		// is a command PRE needet?
                       '', 	                    // optional regex für key
function ($bot, $data) {
  global $redis;
	if($bot->is_op_user($data['user'])) {
    $redis->setnx('operator:test', 0);
		$a = $redis->get('operator:test');
		$b = $redis->incr('operator:test');
    $bot->add_privmsg("Redis test return: $b>$a", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "SetUserAlias",          // command key
									 "LouBot_set_user_alias", // callback function
									 true,                    // is a command PRE needet?
									 '', 		                  // optional regex für key
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
									 '', 		                    // optional regex für key
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