<?php
global $bot;
$bot->add_category('toys', array('humanice' => true, 'spamsafe' => true), PUBLICY);
$bot->add_category('toys2', array('humanice' => true, 'spamsafe' => false), PUBLICY);
// crons

// callbacks
$bot->add_privmsg_hook("Kaffee",                // command key
                       "LouBot_coffee",         // callback function
                       true,                    // is a command PRE needet?
                       '/^(kaf{1,2}e{1,2}|cof{1,2}e{1,2})$/i',  // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $bot->add_allymsg("Ein Kaffee für " . $nick . "? Bitte sehr *Kaffeegeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Bier",                // command key
                       "LouBot_beer",         // callback function
                       true,                  // is a command PRE needet?
                       '/^(bier|beer)$/i',    // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $bot->add_allymsg("Ein Bier für " . $nick . "? Bitte sehr *Biergeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("HappyBirtday",         // command key
                       "LouBot_happy_birthday",// callback function
                       true,                   // is a command PRE needet?
                       '/^birthday$/i',        // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $nick = ucfirst(strtolower($bot->get_random_nick($nick)));
    $bot->add_allymsg("•.¸( `´•.¸ ¸.•´´ )¸.•´´ )");
    $bot->add_allymsg(".¸( `´•.¸HAPPY¸.•´´ )¸.•´´ )");
    $bot->add_allymsg("( `´•.¸( `´•.¸BIRTH-( `´•.¸¸.•");
    $bot->add_allymsg("¸.•´´ )( `´•.¸DAY¸.•´´ )¸.•´´ )");
    $bot->add_allymsg("( `´•.¸( `´•.¸{$nick}¸.•´´ )¸.•´´ )");
    $bot->add_allymsg("´´ )( `´•.¸¸.•´´ )( `´•.¸( `´•");
} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Gold",                  // command key
                       "LouBot_gold",           // callback function
                       true,                    // is a command PRE needet?
                       '/^(gold|goldtaler|goldsack)$/i',  // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $bot->add_allymsg("Ein Sack Gold für " . ucfirst(strtolower($bot->get_random_nick($nick))) . "? Bitte sehr *Goldgeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Keks",                  // command key
                       "LouBot_keks",           // callback function
                       true,                    // is a command PRE needet?
                       '/^(keks|kekse|co{1,2}kie[s]?)$/i',    // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $bot->add_allymsg("Ein Keks für " . ucfirst(strtolower($bot->get_random_nick($nick))) . "? Bitte sehr *Keksgeb*");
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "R3stl33s",                       // command key
                       "LouBot_R3stl33s",                // callback function
                       false,                            // is a command PRE needet?
                       '/r3stl33s/i',                           // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    $bot->add_allymsg("Uhhh was für'n Geheimniss Xd");
  }
}, 'toys2');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Ruh3los",                      // command key
                       "LouBot_Ruh3los",               // callback function
                       false,                          // is a command PRE needet?
                       '/ruh3los/i',                   // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    $bot->add_allymsg("Ruh3los ... Ich wünschte er wäre hier");
  }
}, 'toys2');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Silence",                      // command key
                       "LouBot_silence",               // callback function
                       false,                          // is a command PRE needet?
                       '/silence/i',                   // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    $bot->add_allymsg("... I KIll You!");
  }
}, 'toys2');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Magier",                      // command key
                       "LouBot_mages",                // callback function
                       false,                         // is a command PRE needet?
                       '/(magier|mages)/i',           // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    $bot->add_allymsg("Magier sind gut gegen Schiffe! ...  sagt Bier immer :D");
  }
}, 'toys2');

$bot->add_privmsg_hook("SpamTest",                   // command key
                       "LouBot_spam_test",           // callback function
                       true,                         // is a command PRE needet?
                       '',    // optional regex for key
function ($bot, $data) {
  if($bot->is_ally_user($data['user'])) {
    $bot->add_privmsg("SpamCheck!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_privmsg_hook("Giveaway",              // command key
                       "LouBot_giveaway",       // callback function
                       true,                    // is a command PRE needet?
                       '/^(give|gib|geb|gebe)$/i',    // optioneal regex für key
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

$bot->add_allymsg_hook("Hello",                  // command key
                       "LouBot_hello",           // callback function
                       false,                    // is a command PRE needet?
                       "/^(hi|hallo|hello|moin|tach|tachen|nabend|abend|huhu)[,]? ({$bot->ally_name}|{$bot->ally_shortname})$/i", // optional regex for key
function ($bot, $data) {
  $text[] = 'Hi ';
  $text[] = 'Hallo ';
  $text[] = 'Moin Moin ';
  $nick = $data['params'][0];
  shuffle($text);
  $rand_key = array_rand($text, 1);
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'default'); // explicitly

$bot->add_allymsg_hook("ByeBye",                 // command key
                       "LouBot_bye",             // callback function
                       false,                    // is a command PRE needet?
                       "/^(bb|bye|byebye|goodbye|tschüss|tschau|n8|n8ti|gn8|n8t)[,]? ({$bot->ally_name}|{$bot->ally_shortname})+$/i", // optional regex for key
function ($bot, $data) {
  $text[] = 'bb ';
  $text[] = 'tschau ';
  $text[] = 'n8ti ';
  $text[] = 'n8 ';
  $nick = $data['params'][0];
  shuffle($text);
  $rand_key = array_rand($text, 1);
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'default'); // explicitly


$bot->add_allymsg_hook("Re",                  // command key
                       "LouBot_re",           // callback function
                       false,                 // is a command PRE needet?
                       '/^re[,]?$/i',         // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("wb " . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'toys');

$bot->add_allymsg_hook("Sex",                  // command key
                       "LouBot_sex",           // callback function
                       false,                  // is a command PRE needet?
                       '/sex/i',               // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
  $bot->add_allymsg("Katzenbaaaaabbbbyyyyysss");
}, 'toys');

$bot->add_globlmsg_hook("Willkommen",         // command key
                       "LouBot_startup",      // callback function
                       false,                 // is a command PRE needet?
                       '',                    // optional regex for key
function ($bot, $data) {
  if($data['user'] == '@Info') {
    //$bot->add_allymsg("^^ da isser :)");
  };
}, 'toys');

$bot->add_allymsg_hook("Puschel",                // command key
                       "LouBot_puschel",         // callback function
                       false,                    // is a command PRE needet?
                       '/(puschel|tütü)/i',      // optional regex for key
function ($bot, $data) {
  if(!$bot->is_himself($data['user'])) {
    $text[] = 'Gerade Schoberl geschenkt *gg*';
    $text[] = 'Ein Tütü für Schoberl? Bitte sehr *Tütügeb*';
    shuffle($text);
    $rand_key = array_rand($text, 1);
    $bot->add_allymsg($text[$rand_key]);
  }
}, 'toys');

$bot->add_allymsg_hook("Schugar",                // command key
                       "LouBot_schugar",         // callback function
                       false,                    // is a command PRE needet?
                       '/(sugar|schugar|honey|hony)/i',    // optional regex for key
function ($bot, $data) {
  if(!$bot->is_himself($data['user'])) {
    $text[] = 'Sugar, ahhh';
    $text[] = 'honey honey';
    $text[] = 'you are my candy girl *gg*';
    $text[] = '... and you got me wanting you';
    $text[] = 'I just can\'t believe the loveliness of loving you';
    $text[] = 'i just cant believe its true';
    $text[] = 'I just can believe the wonder of this feeling too';
    $text[] = 'When i kissed you girl i knew how sweet a kiss could be';
    $text[] = 'Like the summer sunshine pour your sweetness over me';
    $text[] = 'ohh,pour your sugar on me honey';
    $text[] = 'pour your sugar on me baby';
    $text[] = 'Im gonna make your life so sweet';

    shuffle($text);
    $rand_key = array_rand($text, 1);
    $bot->add_allymsg($text[$rand_key]);
  }
}, 'toys');

$bot->add_allymsg_hook("VB",                    // command key
                       "LouBot_VB",             // callback function
                       false,                   // is a command PRE needet?
                       '/^(vb|viking|blades)$/i',       // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->setnx('toys:utr', 0);
  if (!$bot->is_himself($data['user'])) {
    $text[] = 'Steinigt sie!';
    $text[] = 'Unter jedem Stein und jedem Gebüsch steckt ein Feind, [i]husch husch[/i]!';
    $text[] = 'Nieder mit dem Pöbel..';
    $text[] = 'Sollen wir sie wieder zu Poden Chleudern?';
    $text[] = 'Jeder nur ein Schwert... und dann feste druff *gg*';
    $text[] = 'Schmeisst diese pösen Purschen zu Poden!';
    $text[] = $bot->get_random_nick($data['user']) . " hat {$data['command']} gesagt";
    shuffle($text);
    $rand_key = array_rand($text, 1);
    if($data['params'][0] && preg_match('/^(preis|preise|gewinn|gewinne|gewinner)$/i', $data['params'][0])) {
      $c = $redis->get('toys:utr');
      switch ($c) {
        case ($c < 100):
          $bot->add_allymsg("TD: nächster Level 100");
          break;
        case ($c > 100 and $c < 200):
          $utr_100 = $redis->get('toys:utr_100');
          $bot->add_allymsg("TD 100: $utr_100");
          $bot->add_allymsg("TD: nächster Level 200");
          break;
        case ($c > 200 and $c < 400):
          $utr_200 = $redis->get('toys:utr_200');
          $bot->add_allymsg("TD 200: $utr_200");
          $bot->add_allymsg("TD: nächster Level 400");
          break;
        case ($c > 400 and $c < 600):
          $utr_400 = $redis->get('toys:utr_400');
          $bot->add_allymsg("TD 400: $utr_400");
          $bot->add_allymsg("TD: nächster Level 600");
          break;
        case ($c > 600 and $c < 800):
          $utr_600 = $redis->get('toys:utr_600');
          $bot->add_allymsg("TD 600: $utr_600");
          $bot->add_allymsg("TD: nächster Level 800");
          break;
        case ($c > 800 and $c < 1000):
          $utr_800 = $redis->get('toys:utr_800');
          $bot->add_allymsg("TD 800: $utr_800");
          $bot->add_allymsg("TD: nächster Level 1000");
          break;
        default:
          $bot->add_allymsg("TD: sollte schon tod sein ;)");
      }
    } else {
      $c = $redis->incr('toys:utr');
      $bot->add_allymsg($text[$rand_key]);
      $nick = $bot->get_random_nick($data['user']);
      if ($c == 100) {$bot->add_allymsg("and the Winner is {$nick} 10.000.000 Gold");$redis->setnx('toys:utr_100', $data['user']);}
      if ($c == 200) {$bot->add_allymsg("and the Winner is {$nick} 20.000.000 Gold");$redis->setnx('toys:utr_200', $data['user']);}
      if ($c == 400) {$bot->add_allymsg("and the Winner is {$nick} 30.000.000 Gold");$redis->setnx('toys:utr_400', $data['user']);}
      if ($c == 600) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:utr_600', $data['user']);}
      if ($c == 800) {$bot->add_allymsg("and the Winner is {$nick} 40.000.000 Gold");$redis->setnx('toys:utr_800', $data['user']);}
      if ($c == 1000) {$bot->add_allymsg("and the Winner is {$nick} 50.000.000 Gold");$redis->setnx('toys:utr_1000', $data['user']);}
    }
  }
}, 'toys');

$bot->add_allymsg_hook("Krieg",                  // command key
                       "LouBot_krieg",           // callback function
                       false,                    // is a command PRE needet?
                       '/^(krieg|rache)$/i',     // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $redis->setnx('toys:krieg', 0);
  $text[] = 'Nieder mit dem Pöbel!!!';
  $text[] = 'Wir sind Vandalen... kein Zurück!';
  $text[] = 'Jeder nur ein Kreuz!';
  $text[] = 'Ist hier Weibsvolk anwesend?';
  $text[] = 'Also gut, ich bin der Messias... und jetzt... ANGRIFF!';
  $text[] = 'Nein, nein, ich hab euch verulkt. In Wirklichkeit ist es eine Kreuzigung.';
  shuffle($text);
  $rand_key = array_rand($text, 1);
  if (!$bot->is_himself($data['user'])) {
    if(preg_match('/^(preis|preise|gewinn|gewinne|gewinner)$/i', $data['params'][0])) {
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
  }
}, 'toys');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Slap",                  // command key
                       "LouBot_slap",           // callback function
                       true,                    // is a command PRE needet?
                       '/^slap[s]?$/i',         // optional regex for key
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
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
    $bot->add_allymsg("schlägt {$nick} " . $text[$rand_key]);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys');

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Danke",                  // command key
                       "LouBot_thx",             // callback function
                       false,                    // is a command PRE needet?
                       "/^(thx|danke|ty)[,]? {$bot->bot_user_name}$/i", // optional regex for key
function ($bot, $data) {
  $text[] = 'NoP ';
  $text[] = '^^ immer wieder gerne ';
  $text[] = 'Gerne ';
  $text[] = 'Wie du mir so ich dir ';
  $text[] = '[i]Return of invenst[/i] ';
  shuffle($text);
  $rand_key = array_rand($text, 1);
  $nick = ($data['params'][0] != '') ? $data['params'][0] : $data['user'];
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'toys');


$bot->add_allymsg_hook("Ressis",                  // command key
                       "LouBot_ressis",           // callback function
                       false,                     // is a command PRE needet?
                       '/^(res{1,2}i[s]?|holz|eisen|stein)[,]?$/i',             // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("einer für Alle, Alle für Einen!");
}, 'toys');

$bot->add_allymsg_hook("Gallis",                  // command key
                       "LouBot_gallis",           // callback function
                       false,                     // is a command PRE needet?
                       '/^gal{1,2}ie?s[,]?$/i',   // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("Gallis am Morgen vertreiben Kummer und Sorgen :)");
}, 'toys');

$bot->add_allymsg_hook("EinSatzMitX",       // command key
                       "LouBot_x",          // callback function
                       false,               // is a command PRE needet?
                       '/^x$/i',            // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("Ein Satz mit x, war wohl nix " . $data['user']);
}, 'toys');

$bot->add_allymsg_hook("AfKippe",                  // command key
                       "LouBot_afkippe",           // callback function
                       false,                      // is a command PRE needet?
                       '/^af[k]{1,2}i[p]{1,2}e[,]?$/i',             // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user'])) {
    $text[] = 'Schäuble freut sich :)';
    $text[] = 'Ich komm mit...';
    $text[] = '*hüstel... kiffen?';
    $text[] = 'guten Zug ' . ucfirst(strtolower($bot->get_random_nick($data['user'])));
    shuffle($text);
    $rand_key = array_rand($text, 1);
    $bot->add_allymsg($text[$rand_key]);
  }
}, 'toys');

$bot->add_allymsg_hook("AfKaffee",                  // command key
                       "LouBot_afkaffee",           // callback function
                       false,                       // is a command PRE needet?
                       '/^af[k]{1,2}a[f]{1,2}[e]{1,2}[,]?$/i',             // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user'])) {
    $text[] = 'Jakobs freut sich :)';
    $text[] = 'Ich komm mit...';
    $text[] = 'Kaffee ist nur schädlich, wenn dir ein ganzer Sack aus dem fünften Stock auf den Kopf fällt. Albert Darboven (*1936)';
    $text[] = 'Coffeinkick für ' . ucfirst(strtolower($bot->get_random_nick($data['user'])));
    shuffle($text);
    $rand_key = array_rand($text, 1);
    $bot->add_allymsg($text[$rand_key]);
  }
}, 'toys');

$bot->add_allymsg_hook("Zitat",                  // command key
                       "LouBot_phrases",         // callback function
                       true,                     // is a command PRE needet?
                       '/^(zitate|zitat|phrase)$/i',   // optional regex for key
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

$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Slogan",                // command key
                       "LouBot_slogans",        // callback function
                       true,                    // is a command PRE needet?
                       '/^(slogan|slogans)$/i', // optional regex for key
function ($bot, $data) {
  global $phrases;
  if (!$bot->is_himself($data['user'])) {
    
    if ($data['params'][0] != '') $persons[] = $data['params'][0];
    else {
      $persons[] = $bot->bot_user_name;
      $persons[] = $bot->ally_name;
      $persons[] = $bot->ally_shortname;
      $persons[] = $data['user'];
    }
    shuffle($persons);
    $rand_key_persons = array_rand($persons, 1);

    $verbs[] = 'Palast';
    $verbs[] = 'LoU';
    $verbs[] = $bot->bot_user_name;
    shuffle($verbs);
    $rand_key_verbs = array_rand($verbs, 1);
    
    $request = array('adjektiv' => 'Lord of Ultima',
                     'person'   => $persons[$rand_key_persons],
                     'verb'     => $verbs[$rand_key_verbs]
    );
    $response = slogan_call($request);
    print_r($response);
    
    if ($response) {
      preg_match('/<b>(.*)<\/b>/sim', $response, $matches);
      $bot->log("LoU -> get response from Sloganizer\n\r");
      $bot->add_allymsg($matches[1]);
    } else return $bot->add_allymsg(magic_slogan()); // fallback
  }
}, 'toys');

if(!function_exists('slogan_call')) {
  function slogan_call($request) {
      $_map_fields = array_map(create_function('$key, $value', 'return $key."=".urlencode($value);'), array_keys($request), array_values($request));
      echo implode("&", $_map_fields);
      $header[] = "Content-Type: application/x-www-form-urlencoded; charset=UTF-8";
      $header[] = "X-Requested-With: XMLHttpRequest";
      $header[] = "Content-length: ".strlen(implode("&", $_map_fields));
      $url = "http://www.poetron-zone.de/php/sloganizer_generate.php";
      $_useragent = 'Mozilla/4.0 (compatible; MSIE 5.0; Windows NT 5.0)';
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_USERAGENT, $_useragent);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $_map_fields));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_REFERER, 'http://www.poetron-zone.de/sloganizer.php'); 
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120); // Timeout if it takes too long
      curl_setopt($ch, CURLOPT_TIMEOUT, 120);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

      $data = curl_exec($ch);       
      if (curl_errno($ch) || !$data) {
          print curl_error($ch);
          return false;
      } else {
          curl_close($ch);
          return $data;
      }
  }
}
if(!function_exists('magic_slogan')) {
  function magic_slogan() {
    // de
    $text_de[] = 'Lord of Ultima bleibt Lord of Ultima: Doch Alsius hilft!';
    $text_de[] = 'Der Lord of Ultimae Bürger will Alsius. Warum wohl?';
    $text_de[] = 'Besser nix sagen *hmpf*';
    $text_de[] = 'Alse mit Alsius - LoU werden mit Stil.';
    $text_de[] = 'Alsius - Für den anspruchsvollen Herren: Alse damit es knackt!';
    $text_de[] = 'Spaß mit Alsius, gelassen und LoU!';
    $text_de[] = 'Komfort kennt keine Grenzen - Alse mit Alsius!';
    $text_de[] = '2011! Das Jahr des Aufstiegs von Alsius.';
    $text_de[] = 'Dir antworte ich nicht!';
    $text_de[] = 'Sorry, jetzt muss ich gerade was anderes tun...';
    
    $text = (!empty($text_{BOT_LANG})) ? $text_{BOT_LANG} : $text_en;
    shuffle($text);
    $rand_key = array_rand($text, 1);
    return $text[$rand_key];
  }
}

$bot->add_allymsg_hook("lol", // command key
                "LouBot_lol", // callback function
                false,        // is a command PRE needet?
                '/^lol$/i',   // optional regex for key
  function ($bot, $data) {
    if(!$bot->is_himself($data['user'])) {
      $nick = $data['user'];
      $text[] = 'hihihi';
      $text[] = 'rofl!';
      $text[] = "omg {$nick} hat lol gesagt";
      $test[] = 'LOL!';
      shuffle($text);
      $rand_key = array_rand($text, 1);
      $bot->add_allymsg($text[$rand_key]);
    }
}, 'toys');

$bot->add_allymsg_hook("liebe", // command key
                "LouBot_liebe", // callback function
                false,          // is a command PRE needet?
                '/^(lieb|liebe)$/i', // optional regex for key
  function ($bot, $data) {
    if(!$bot->is_himself($data['user'])) {
      $nick = $data['user'];
      $text[] = 'liebe liegt in der Luft!';
      $text[] = "ich mag dich {$nick}";
      $text[] = 'isch liebe den Duft von anstürmenden Berserkern um 10:01!';
      $text[] = 'ich bin immer lieb!';
      shuffle($text);
      $rand_key = array_rand($text, 1);
      $bot->add_allymsg($text[$rand_key]);
    }
}, 'toys');

$bot->add_allymsg_hook("morgen", // command key
         "LouBot_morgen_saecke", // callback function
                          false, // is a command PRE needet?
        '/^morgen ihr säcke$/i', // optional regex for key
  function ($bot, $data) {
    if(!$bot->is_himself($data['user'])) {
      $bot->add_allymsg('morgen du Sack.');
    }
}, 'toys');
?>