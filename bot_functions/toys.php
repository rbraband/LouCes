<?php
global $bot;
$bot->add_category('toys', array('humanice' => true, 'spamsafe' => true), PUBLICY);
$bot->add_privmsg_hook("Kaffee",                // command key
                       "LouBot_coffee",         // callback function
                       true,                    // is a command PRE needet?
                       '/^(kaf{1,2}e{1,2}|cof{1,2}e{1,2})$/i',  // optional regex für key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = $data['params'][0];
    $bot->add_allymsg("Ein Kaffee für " . $nick . "? Bitte sehr *Kaffeegeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Bier",                // command key
                       "LouBot_beer",         // callback function
                       true,                  // is a command PRE needet?
                       '/^(bier|beer)$/i',    // optional regex für key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = $data['params'][0];
    $bot->add_allymsg("Ein Bier für " . $nick . "? Bitte sehr *Biergeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Gold",                	// command key
                       "LouBot_gold",         	// callback function
                       true,                    // is a command PRE needet?
                       '/^(gold|goldtaler|goldsack)$/i',  // optional regex für key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = $data['params'][0];
    $bot->add_allymsg("Ein Sack Gold für " . $nick . "? Bitte sehr *Goldgeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Keks",                	// command key
                       "LouBot_keks",         	// callback function
                       true,                    // is a command PRE needet?
                       '/^(keks|kekse|co{1,2}kie[s]?)$/i',  	// optional regex für key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = $data['params'][0];
    $bot->add_allymsg("Ein Keks für " . $nick . "? Bitte sehr *Keksgeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("SpamTest",                	// command key
                       "LouBot_spam_test",         	// callback function
                       true,                    // is a command PRE needet?
                       '',  	// optional regex für key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $bot->add_privmsg("SpamCheck!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Giveaway",              // command key
                       "LouBot_giveaway",       // callback function
                       true,                    // is a command PRE needet?
                       '/^(give|gib|geb|gebe)$/i',  	// optioneal regex für key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $giveaway = '';
    $nick = array_shift($data['params']);
		$nick = ($bot->is_ally_user($nick)) ? ucfirst(strtolower($bot->get_random_nick($nick))) : $nick;
    foreach($data['params'] as $item){
      $giveaway .= ucfirst($item);
    }
    if ($giveaway == '') $bot->add_privmsg("zu wenig Parameter (!Gib Alse 100 Gold)!", $data['user']);
    else $bot->add_allymsg(implode(' ',$data['params'])." für " . $nick . "? Bitte sehr *".$giveaway."geb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_allymsg_hook("Hello",                	// command key
                       "LouBot_hello",         	// callback function
                       false,                   // is a command PRE needet?
                       '/^(hi|hallo|hello|moin|tach)[,]?$/i', // optional regex für key
function ($bot, $data) {
  $text[] = 'Hi ';
  $text[] = 'Hallo ';
  $text[] = 'Moin ';
  $text[] = 'Tach ';
  $nick = $data['params'][0];
  shuffle($text);
  $rand_key = array_rand($text, 1);
  if (strtoupper($nick) == strtoupper($bot->bot_user_name) && !$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'default'); // explicitly

$bot->add_allymsg_hook("Danke",                	// command key
                       "LouBot_thx",         	  // callback function
                       false,                   // is a command PRE needet?
                       '/^(thx|danke|ty)[,]?$/i', // optional regex für key
function ($bot, $data) {
  $text[] = 'NoP ';
  $text[] = '^^ immer wieder  ';
	$text[] = 'Gerne ';
  $text[] = 'Wie du mir so ich dir ';
  $text[] = '[i]Return of invenst[/i] ';
  shuffle($text);
  $rand_key = array_rand($text, 1);
  $nick = $data['params'][0];
  if (strtoupper($nick) == strtoupper($bot->bot_user_name) && !$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'toys');

$bot->add_allymsg_hook("Re",                	// command key
                       "LouBot_re",         	// callback function
                       false,                 // is a command PRE needet?
                       '/^re[,]?$/i', 				    // optional regex für key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
  $bot->add_allymsg("wb " . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'toys');

$bot->add_globlmsg_hook("Willkommen",         // command key
                       "LouBot_startup",      // callback function
                       false,                 // is a command PRE needet?
                       '', 				            // optional regex für key
function ($bot, $data) {
  if($data['user'] == '@Info') {
	  //$bot->add_allymsg("^^ da isser :)");
  };
}, 'toys');

$bot->add_allymsg_hook("UTR",                	// command key
                       "LouBot_utr",         	// callback function
                       false,                 // is a command PRE needet?
                       '/^utr$/i', 				    // optional regex für key
function ($bot, $data) {
  global $redis;
  if (!$bot->is_himself($data['user'])) {
    if ($redis->status()) {
			$redis->setnx('toys:utr', 0);
			if(preg_match('/^(preis|preise|gewinn|gewinner)$/i', $data['params'][0])) {
				$c = $redis->get('toys:utr');
				switch ($c) {
					case ($c < 100):
						$bot->add_allymsg("UTR: nächster Level 100");
						break;
					case ($c > 100 and $c < 200):
						$utr_100 = $redis->get('toys:utr_100');
						$bot->add_allymsg("UTR 100: $utr_100");
						$bot->add_allymsg("UTR: nächster Level 200");
						break;
					case ($c > 200 and $c < 400):
						$utr_200 = $redis->get('toys:utr_200');
						$bot->add_allymsg("UTR 200: $utr_200");
						$bot->add_allymsg("UTR: nächster Level 400");
						break;
					case ($c > 400 and $c < 600):
						$utr_400 = $redis->get('toys:utr_400');
						$bot->add_allymsg("UTR 400: $utr_400");
						$bot->add_allymsg("UTR: nächster Level 600");
						break;
					case ($c > 600 and $c < 800):
						$utr_600 = $redis->get('toys:utr_600');
						$bot->add_allymsg("UTR 600: $utr_600");
						$bot->add_allymsg("UTR: nächster Level 800");
						break;
					case ($c > 800 and $c < 1000):
						$utr_800 = $redis->get('toys:utr_800');
						$bot->add_allymsg("UTR 800: $utr_800");
						$bot->add_allymsg("UTR: nächster Level 1000");
						break;
					default:
						$bot->add_allymsg("UTR: sollte schon tod sein ;)");
				}
			} else {
				$c = $redis->incr('toys:utr');
				$bot->add_allymsg("Tod UTR!!!");
				$nick = $bot->get_random_nick($data['user']);
				if ($c == 100) {$bot->add_allymsg("and the Winner is {$nick} 10.000.000 Gold");$redis->setnx('toys:utr_100', $data['user']);}
				if ($c == 200) {$bot->add_allymsg("and the Winner is {$nick} 20.000.000 Gold");$redis->setnx('toys:utr_200', $data['user']);}
				if ($c == 400) {$bot->add_allymsg("and the Winner is {$nick} 30.000.000 Gold");$redis->setnx('toys:utr_400', $data['user']);}
				if ($c == 600) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:utr_600', $data['user']);}
				if ($c == 800) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:utr_800', $data['user']);}
				if ($c == 1000) {$bot->add_allymsg("and the Winner is {$nick} 50.000.000 Gold");$redis->setnx('toys:utr_1000', $data['user']);}
			}
		} else $bot->add_allymsg("Tod UTR!!!");
  }
}, 'toys');

$bot->add_allymsg_hook("Krieg",                	// command key
                       "LouBot_krieg",         	// callback function
                       false,                 	// is a command PRE needet?
                       '/^krieg$/i', 				    // optional regex für key
function ($bot, $data) {
	global $redis;
	$text[] = 'Nieder mit dem Schlumpf!!!';
	$text[] = 'Wir sind SPARTA... äh... ALSEN!!!';
	$text[] = 'Jeder nur ein Kreuz!';
	$text[] = 'Ist hier Weibsvolk anwesend?';
	$text[] = 'Also gut, ich bin der Messias... und jetzt... ANGRIFF!';
	$text[] = 'Nein, nein, ich hab euch verulkt. In Wirklichkeit ist es Kreuzigung.';
	shuffle($text);
  $rand_key = array_rand($text, 1);
  if (!$bot->is_himself($data['user'])) {
		if ($redis->status()) {
			$redis->setnx('toys:krieg', 0);
			if(preg_match('/^(preis|preise|gewinn|gewinner)$/i', $data['params'][0])) {
				$c = $redis->get('toys:krieg');
				switch ($c) {
					case ($c < 100):
						$bot->add_allymsg("Krieg: nächster Level 100");
						break;
					case ($c > 100 and $c < 200):
						$krieg_100 = $redis->get('toys:krieg_100');
						$bot->add_allymsg("Krieg 100: $krieg_100");
						$bot->add_allymsg("Krieg: nächster Level 200");
						break;
					case ($c > 200 and $c < 400):
						$krieg_200 = $redis->get('toys:krieg_200');
						$bot->add_allymsg("Krieg 200: $krieg_200");
						$bot->add_allymsg("Krieg: nächster Level 400");
						break;
					case ($c > 400 and $c < 600):
						$krieg_400 = $redis->get('toys:krieg_400');
						$bot->add_allymsg("Krieg 400: $krieg_400");
						$bot->add_allymsg("Krieg: nächster Level 600");
						break;
					case ($c > 600 and $c < 800):
						$krieg_600 = $redis->get('toys:krieg_600');
						$bot->add_allymsg("Krieg 600: $krieg_600");
						$bot->add_allymsg("Krieg: nächster Level 800");
						break;
					case ($c > 800 and $c < 1000):
						$krieg_800 = $redis->get('toys:krieg_800');
						$bot->add_allymsg("Krieg 800: $krieg_800");
						$bot->add_allymsg("Krieg: nächster Level 1000");
						break;
					default:
						$bot->add_allymsg("Krieg: sollte schon vorbei sein ;)");
				}
			} else {
				$c = $redis->incr('toys:krieg');
				$bot->add_allymsg($text[$rand_key]);
				$nick = $bot->get_random_nick($data['user']);
				if ($c == 100) {$bot->add_allymsg("and the Winner is {$nick} 10.000.000 Gold");$redis->setnx('toys:krieg_100', $data['user']);}
				if ($c == 200) {$bot->add_allymsg("and the Winner is {$nick} 20.000.000 Gold");$redis->setnx('toys:krieg_200', $data['user']);}
				if ($c == 400) {$bot->add_allymsg("and the Winner is {$nick} 30.000.000 Gold");$redis->setnx('toys:krieg_400', $data['user']);}
				if ($c == 600) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:krieg_600', $data['user']);}
				if ($c == 800) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:krieg_800', $data['user']);}
				if ($c == 1000) {$bot->add_allymsg("and the Winner is {$nick} 50.000.000 Gold");$redis->setnx('toys:krieg_1000', $data['user']);}
			}
		} else $bot->add_allymsg($text[$rand_key]);
	}
}, 'toys');

$bot->add_allymsg_hook("IMP21",                	// command key
                       "LouBot_imp21",         	// callback function
                       false,                 	// is a command PRE needet?
                       '/^(imp21|imp)$/i', 			// optional regex für key
function ($bot, $data) {
	global $redis;
  if (!$bot->is_himself($data['user'])) {
		if ($redis->status()) {
			$redis->setnx('toys:imp21', 0);
			$text[] = 'Steinigt sie!';
			$text[] = 'Nieder mit dem Pöbel..';
			$text[] = 'Sollen wir sie wieder zu Poden Chleudern?';
			$text[] = 'Jeder nur ein Schwert... und dann feste druff *gg*';
			$text[] = 'Schmeisst diese pösen Purschen zu Poden!';
			$text[] = $bot->get_random_nick($data['user']) . " hat {$data['params'][0]} gesagt";
			shuffle($text);
			$rand_key = array_rand($text, 1);
			if(preg_match('/^(preis|preise|gewinn|gewinner)$/i', $data['params'][0])) {
				$c = $redis->get('toys:imp21');
				switch ($c) {
					case ($c < 100):
						$bot->add_allymsg("IMP21: nächster Level 100");
						break;
					case ($c > 100 and $c < 200):
						$imp21_100 = $redis->get('toys:imp21_100');
						$bot->add_allymsg("IMP21 100: $imp21_100");
						$bot->add_allymsg("IMP21: nächster Level 200");
						break;
					case ($c > 200 and $c < 400):
						$imp21_200 = $redis->get('toys:imp21_200');
						$bot->add_allymsg("IMP21 200: $imp21_200");
						$bot->add_allymsg("IMP21: nächster Level 400");
						break;
					case ($c > 400 and $c < 600):
						$imp21_400 = $redis->get('toys:imp21_400');
						$bot->add_allymsg("IMP21 400: $imp21_400");
						$bot->add_allymsg("IMP21: nächster Level 600");
						break;
					case ($c > 600 and $c < 800):
						$imp21_600 = $redis->get('toys:imp21_600');
						$bot->add_allymsg("IMP21 600: $imp21_600");
						$bot->add_allymsg("IMP21: nächster Level 800");
						break;
					case ($c > 800 and $c < 1000):
						$imp21_800 = $redis->get('toys:imp21_800');
						$bot->add_allymsg("IMP21 800: $imp21_800");
						$bot->add_allymsg("IMP21: nächster Level 1000");
						break;
					default:
						$bot->add_allymsg("IMP21: sollte schon tod sein ;)");
				}
			} else {
				$c = $redis->incr('toys:imp21');
				$bot->add_allymsg($text[$rand_key]);
				$nick = $bot->get_random_nick($data['user']);
				if ($c == 100) {$bot->add_allymsg("and the Winner is {$nick} 10.000.000 Gold");$redis->setnx('toys:imp21_100', $data['user']);}
				if ($c == 200) {$bot->add_allymsg("and the Winner is {$nick} 20.000.000 Gold");$redis->setnx('toys:imp21_200', $data['user']);}
				if ($c == 400) {$bot->add_allymsg("and the Winner is {$nick} 30.000.000 Gold");$redis->setnx('toys:imp21_400', $data['user']);}
				if ($c == 600) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:imp21_600', $data['user']);}
				if ($c == 800) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:imp21_800', $data['user']);}
				if ($c == 1000) {$bot->add_allymsg("and the Winner is {$nick} 50.000.000 Gold");$redis->setnx('toys:imp21_1000', $data['user']);}
			}
		} else $bot->add_allymsg($text[$rand_key]);
	}
}, 'toys');

$bot->add_privmsg_hook("Slap",                	// command key
                       "LouBot_slap",         	// callback function
                       true,                 		// is a command PRE needet?
                       '/^slap[s]?$/i', 				// optional regex für key
function ($bot, $data) {
	$text[] = 'mit Erfahrung!';
	$text[] = 'mit seinem Witz!';
	$text[] = 'mit seiner Sammlung Abziehbilder!';
	$text[] = 'beim schummeln ;)';
	$text[] = 'mit einer Bratpfanne *zonk*';
	$text[] = 'mit ner dreischwänzigen Katze *huh*';
	$text[] = 'auf jede Art.';
	$text[] = 'beim weit pi*****';
	$text[] = 'im nächsten Leben :/';
	$text[] = 'an Unerfahrung ;)';
	$text[] = 'an Überheblichkeit.';
	$text[] = 'mit Weitsicht.';
	$text[] = 'mit ner Forelle *Yihaa*';
	$text[] = 'im Leben... nicht :/';
	shuffle($text);
  $rand_key = array_rand($text, 1);
	if (!$bot->is_himself($data['user'])) {
		$nick = $data['params'][0];
		$bot->add_allymsg("schlägt {$nick} " . $text[$rand_key]);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');




$bot->add_allymsg_hook("Ressis",                	// command key
                       "LouBot_ressis",         	// callback function
                       false,                 		// is a command PRE needet?
                       '/^(res{1,2}i[s]?|holz|eisen|stein)[,]?$/i', 				    // optional regex für key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("einer für Alle, Alle für Einen!");
}, 'toys');

$bot->add_allymsg_hook("Gallis",                	// command key
                       "LouBot_gallis",         	// callback function
                       false,                 		// is a command PRE needet?
                       '/^gal{1,2}is[,]?$/i', 				// optional regex für key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("Gallis am Morgen vertreiben Kummer und Sorgen :)");
}, 'toys');

$bot->add_allymsg_hook("EinSatzMitX",       // command key
                       "LouBot_x",         	// callback function
                       false,               // is a command PRE needet?
                       '/^x$/i', 				    // optional regex für key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("Ein Satz mit x, war wohl nix " . $data['user']);
}, 'toys');

$bot->add_allymsg_hook("AfKippe",                	// command key
                       "LouBot_afkippe",         	// callback function
                       false,                 		// is a command PRE needet?
                       '/^afkippe[,]?$/i', 				    // optional regex für key
function ($bot, $data) {
  if (!$bot->is_himself($data['user'])) {
    $text[] = 'Schäuble freut sich :)';
    $text[] = 'Ich komm mit...';
    $text[] = 'guten Zug ' . ucfirst(strtolower($bot->get_random_nick($data['user'])));
    shuffle($text);
    $rand_key = array_rand($text, 1);
    $bot->add_allymsg($text[$rand_key]);
  }
}, 'toys');

$bot->add_allymsg_hook("Zitat",                	// command key
                       "LouBot_phrases",        // callback function
                       true,                 		// is a command PRE needet?
                       '/^(zitate|zitat|phrase)$/i', 	// optional regex für key
function ($bot, $data) {
  global $phrases;
  if (!$bot->is_himself($data['user'])) {
    if (empty($phrases)) {
      $lines = file(PERM_DATA.'phrases.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line_num => $line) {
        if ($line[0] != '#') $phrases[$line_num] = htmlspecialchars($line);
      }
    }
    shuffle($phrases);
    $rand_key = array_rand($phrases, 1);
    $bot->add_allymsg($phrases[$rand_key]);
  }
}, 'toys');
/*
$bot->add_allymsg_hook("Magic8Ball",                	// command key
                       "LouBot_magic8ball",         	// callback function
                       false,                 		    // is a command PRE needet?
                       '/^('.$bot->bot_user_name.'|Frage)[,:\?]+$/i',  // optional regex für key
function ($bot, $data) {
  if(preg_match('/\?/', $data['message']) && !$bot->is_himself($data['user'])) {
    $keys = array('wer', 'wo', 'wie', 'was', 'wann', 'warum', 'wieso', 'ist', 'kann');      
    $text[] = 'Da antworte ich lieber nicht, versuche es erneut :|';
    $text[] = 'Darauf reagier ich gar nicht...';
    $text[] = 'Besser nix sagen *hmpf*';
    $text[] = 'Konzentriere dich auf das was du eigentlich Fragen wolltest!';
    $text[] = 'Wie ich es sehe, ja :)';
    $text[] = 'Es ist sicher B-)';
    $text[] = 'Ja - auf jeden Fall.';
    $text[] = 'Höchstwahrscheinlich';
    $text[] = 'Empfehlenswert ;)';
    $text[] = 'Die Zeichen deuten auf - [b]ja[/b]';
    $text[] = 'Ohne Zweifel :-7';
    $text[] = 'Ohne Mich :-7';
    $text[] = 'Da kannst du dich drauf verlassen :)';
    $text[] = 'Es ist entschieden, so oder so *gg*';
    $text[] = 'Da verlässt sich keiner drauf =P';
    $text[] = 'Meine Antwort ist ... [i]NULL[/i]';
    $text[] = 'Meine Quellen sagen nein!';
    $text[] = 'nicht so empfehlenswert :/';
    $text[] = 'Sehr zweifelhaft =O';
    $text[] = 'glaub ich nicht ^^';
    $text[] = 'Wo ist denn das Problem?';
    $text[] = 'Wie jetzt?';
    $text[] = 'Dir antworte ich nicht!';
    $text[] = 'Sorry, jetzt muss ich gerade was anderes tun...';
    shuffle($text);
    $rand_key = array_rand($text, 1);
    $bot->add_allymsg($text[$rand_key]);
  };
});
*/
?>