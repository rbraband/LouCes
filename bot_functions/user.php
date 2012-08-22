<?php
global $bot;
$bot->add_category('user', array(), PUBLICY);

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "UV",               // command key
									 "LouBot_uv",           // callback function
									 false,                 // is a command PRE needet?
									 '/^[!]?UV$/i', 		      // optional regex für key
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
      $uvid = $redis->GET("user:{$uid}:uv");
      if ($uvid) {
        $uv = $redis->HGET("user:{$uvid}:data", 'name');
        $nick = $redis->HGET("user:{$uid}:data", 'name');
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg("{$nick}'s UV: [spieler]{$uv}[/spieler]");
        else 
          $bot->add_privmsg((($nick == $data['user']) ? "Deine UV " : "{$nick}'s UV ") . "[spieler]{$uv}[/spieler]", $data['user']);
        return true;
      } else {
        $nick = $redis->HGET("user:{$uid}:data", 'name');
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg("{$nick} hat keine UV gesetzt!");
        else 
          $bot->add_privmsg((($nick == $data['user']) ? "Du hast keine UV gesetzt!" : "{$nick} hat keine UV gesetzt!"), $data['user']);
        return true;
      }
    } else if ($bot->is_ally_user($data['params'][0])) {
      $uid = $bot->get_user_id($data['user']);
      $uvid = $bot->get_user_id($data['params'][0]);
      if ($uid == $uvid) {
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg("{$data['user']}, du kannst dir nicht selbts UV geben!.");
        else 
          $bot->add_privmsg("Du kannst dir nicht selbts UV geben!", $data['user']);        
        return true;
      }
      $uv = $redis->HGET("user:{$uvid}:data", 'name');
      $redis->SET("user:{$uid}:uv", $uvid);
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg("{$data['user']}'s UV [spieler]{$uv}[/spieler] gesetzt.");
      else 
        $bot->add_privmsg("Deine UV [spieler]{$uv}[/spieler] ist gesetzt.", $data['user']);        
      return true;
    } else if (strtoupper($data['params'][0]) == 'DEL') {
      $uid = $bot->get_user_id($data['user']);
      $uvid = $redis->GET("user:{$uid}:uv");
      if ($uvid) {
        $uv = $redis->HGET("user:{$uvid}:data", 'name');
        $nick = $redis->HGET("user:{$uid}:data", 'name');
        $del = $redis->DEL("user:{$uid}:uv");
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg("{$data['user']}'s UV [spieler]{$uv}[/spieler] gelöscht.");
        else 
          $bot->add_privmsg("Deine UV [spieler]{$uv}[/spieler] ist gelöscht.", $data['user']);
        return true;
      } else {
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg("{$data['user']} hat keine UV gesetzt!");
        else 
          $bot->add_privmsg("Du hast keine UV gesetzt!", $data['user']);
        return true;
      }
    }
    $bot->add_privmsg('Alias Fehler: falsche Parameter!', $data['user']);

	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'user');

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
      if (!preg_match('/^[a-zA-Z]{1}[a-zA-Z0-9-_.]{1,15}$/', $data['params'][0]) || strtoupper($data['params'][0]) == 'DEL') {
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
          $bot->add_privmsg("Dein Alias [i]{$_alias}[/i] ist gesetzt.", $data['user']);        
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

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "Seen",             		// command key
									 "LouBot_seen",      		// callback function
									 true,                  // is a command PRE needet?
									 '/^(lastseen|seen)$/i',// optional regex für key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
	if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
		if ($bot->is_ally_user($data['params'][0])) {
			$uid = $bot->get_user_id($data['params'][0]);
			$nick = $redis->HGET("user:{$uid}:data", 'name');
			$lastlogin = $redis->HGET("user:{$uid}:data", 'lastlogin');
			$summer = substr(date('O', strtotime($lastlogin)),0,3);
      $date = date('d.M.Y H:i:s', strtotime("$lastlogin $summer hours"));
			$message = ucfirst(mb_strtolower($data['params'][0])) . "'s letzter Login war {$date}";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
		}
	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'user');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "Chat",             		// command key
									 "LouBot_chat",      		// callback function
									 true,                  // is a command PRE needet?
									 '/^chat$/', 		        // optional regex für key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
	if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
		if ($bot->is_ally_user($data['params'][0])) {
			$uid = $bot->get_user_id($data['params'][0]);
			$nick = $redis->HGET("user:{$uid}:data", 'name');
			$lastchat = $redis->HGET("user:{$uid}:data", 'lastchat');
			$date = date('d.M.Y H:i:s', strtotime($lastchat));
			$message = ucfirst(mb_strtolower($data['params'][0])) . "'s letzter Chat war {$date}";
        if ($data["channel"] == ALLYIN)
          $bot->add_allymsg($message);
        else 
          $bot->add_privmsg($message, $data['user']);
		}
	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'user');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "LastChat",             		// command key
									 "LouBot_last_chat",      	// callback function
									 false,                  		// is a command PRE needet?
									 '/.*/i', 		              // optional regex für key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
	if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
		$uid = $bot->get_user_id($data['user']);
		$redis->HMSET("user:{$uid}:data", array(
      'lastchat' => date("m/d/Y H:i:s")
    ));
	};
}, 'user');
?>