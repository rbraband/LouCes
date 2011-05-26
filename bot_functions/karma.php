<?php
global $bot;
$bot->add_category('karma', array(), PUBLICY);

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "Karma",               // command key
									 "LouBot_karma",        // callback function
									 true,                  // is a command PRE needet?
									 '/^karma$/i', 				  // optional regex für key
function ($bot, $data) {
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
		$nick = (!empty($data['params'][0]) && $bot->is_ally_user($data['params'][0])) ? $data['params'][0] : $data['user'];
		$max = 10;
		$karma = 5;
		$prozent = 50;
		if ($data["channel"] == ALLYIN)
			$bot->add_allymsg("{$nick}'s Karma: " . str_repeat('|', $karma) . str_repeat('&#160;', ($max-$karma)) . " {$prozent}%");
		else 
			$bot->add_privmsg((($nick == $data['user']) ? "Dein" : "{$nick}'s") . " Karma: " . str_repeat('|', $karma) . str_repeat('&#160;', ($max-$karma)) . " {$prozent}%", $data['user']);
	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'karma');

?>