<?php
global $bot;
$bot->add_category('user', array(), PUBLICY);

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "Alias",               // command key
									 "LouBot_alias",        // callback function
									 false,                 // is a command PRE needet?
									 '/^[!]?Alias$/', 		  // optional regex für key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    if ($data['command'][0] != PRE) {
      $user = false;
      if (!empty($data['params'][0])) {
        if ($bot->is_ally_user($data['params'][0]))
          $user = $data['params'][0];
        else {
          $message = "Der Alias [i]" . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist nicht belegt!";
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg($message);
          else 
            $bot->add_privmsg($message, $data['user']);
          return true;
        }
      } else $user = $data['user'];
      $uid = $bot->get_user_id($user);
      $aliase = $redis->SMEMBERS("user:{$uid}:alias");
      $nick = $redis->HGET("user:{$uid}:data", 'name');
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg("{$nick}'s Aliase: [i]" . ucwords(mb_strtolower(implode(', ', $aliase))) . "[/i]");
      else 
        $bot->add_privmsg((($nick == $data['user']) ? "Deine Aliase: [i]" : "{$nick}'s Aliase: [i]") . ucwords(mb_strtolower(implode(', ', $aliase))) . "[/i]", $data['user']);
      return true;
    } else if (!$bot->is_ally_user($data['params'][0])) {
      if (!preg_match('/^[a-zA-Z]{1}[a-zA-Z0-9-_.]{1,15}$/', $data['params'][0])) {
        $message = 'Der Alias [i]' . mb_strtolower($data['params'][0]) . '[/i] ist ungültig!';
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
        return true;
      }
      $uid = $bot->get_user_id($data['user']);
      $alias = mb_strtoupper($data['params'][0]);
      $insert = $redis->HMSET("aliase", array(
        $alias => $uid
      ));
      if ($insert) {
        $redis->SADD("user:{$uid}:alias", $alias);
        $_alias = ucfirst(mb_strtolower($alias));
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg("{$data['user']}'s Alias [i]{$_alias}[/i] gesetzt.");
        else 
          $bot->add_privmsg("Dein Alias [i]{$_alias}[/i] ist gesetzt.");        
        return true;
      }
    } else {
      $uid = $bot->get_user_id($data['params'][0]);
      $nick = $redis->HGET("user:{$uid}:data", 'name');
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg('Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist von {$nick} belegt!");
      else 
        $bot->add_privmsg('Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist von ".(($nick == $data['user']) ? 'Dir' : $nick)." belegt!", $data['user']);
      return true;
    }
    $bot->add_privmsg('Alias Fehler: falsche Parameter!', $data['user']);

	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'user');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "DeAlias",             // command key
									 "LouBot_dealias",      // callback function
									 true,                  // is a command PRE needet?
									 '', 		                // optional regex für key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    if ($bot->is_ally_user($data['params'][0])) {
      $uid = $bot->get_user_id($data['params'][0]);
      $nick = $redis->HGET("user:{$uid}:data", 'name');
      if (mb_strtoupper($data['user']) == mb_strtoupper($data['params'][0])) {
        $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] kann nicht gelöscht werden!";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
      } else if ($nick != $data['user']) {
        $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist von {$nick} belegt!";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
      } else {
        $alias = mb_strtoupper($data['params'][0]);
        $redis->HDEL("aliase", $alias);
        $redis->SREM("user:{$uid}:alias", $alias);
        $message = ' Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist gelöscht!";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg('Der'.$message);
        else 
          $bot->add_privmsg('Dein'.$message, $data['user']);
      }
      return true;
    } else {
      $message = 'Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist nicht belegt!";
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg($message);
      else 
        $bot->add_privmsg($message);
      return true;
    }
    $bot->add_privmsg('Alias Fehler: falsche Parameter!', $data['user']);

	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'user');
?>