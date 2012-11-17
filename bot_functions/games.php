<?php
global $bot;
$bot->add_category('games', array('humanice' => false, 'spamsafe' => true), PUBLICY);
$bot->add_category('fast_games', array('humanice' => false, 'spamsafe' => false), PUBLICY);
// crons
$bot->add_tick_event(Cron::TICK0,                 // Cron key
                    "ForceQuizzerCheck",          // command key
                    "LouBot_force_quizzer_check", // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $games_key = "games:alliance:{$bot->ally_id}";
  $time_to_solve = time() - $redis->GET("{$games_key}:quizzer:start");
  if ($redis->GET("{$games_key}:running") == QUIZZER && $time_to_solve >= 60) return quizzer_main4(null, null, true);
}, 'fast_games');

// callbacks
$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                       "Points",                  // command key
                       "LouBot_points",           // callback function
                       true,                      // is a command PRE needet?
                       '/^(points|punkte)$/i',    // optional regex for key
function ($bot, $data) {
  global $redis, $hwords;
  $games_key = "games:alliance:{$bot->ally_id}";
  $commands = array('hangman', 'quiz', 'slot');
  if(!$bot->is_himself($data['user'])) {
    if (in_array(strtolower($data['params'][0]), $commands) || empty($data['params'][0])) {
      switch (strtolower($data['params'][0])) {
        case 'hangman':
          if (!($points = $redis->ZSCORE("{$games_key}:hangman:points", $data['user']))) $points = 0;
          $rank = $redis->ZREVRANK("{$games_key}:hangman:points", $data['user']);
          break;
        case 'quiz':
          if (!($points = $redis->ZSCORE("{$games_key}:quizzer:points", $data['user']))) $points = 0;
          $rank = $redis->ZREVRANK("{$games_key}:quizzer:points", $data['user']);
          break;
        case 'slot':
          if (!($points = $redis->ZSCORE("{$games_key}:slot:points", $data['user']))) $points = 0;
          $rank = $redis->ZREVRANK("{$games_key}:slot:points", $data['user']);
          break;
        default:
          if (!($points = $redis->ZSCORE("points:alliance:{$bot->ally_id}", $data['user']))) $points = 0;
          $rank = $redis->ZREVRANK("points:alliance:{$bot->ally_id}", $data['user']);
      }
      $game = (!empty($data['params'][0])) ? ' ' . ucfirst($data['params'][0]) : ' Games';
    }
    if ($rank === false) $rank = '-'; else $rank +=1;
    if ($data["channel"] == ALLYIN)
      $bot->add_allymsg("{$data['user']}, du hast {$points}{$game} Punkte und bist auf Platz: {$rank}");
    else 
      $bot->add_privmsg("Du hast {$points}{$game} Punkte und bist auf Platz: {$rank}", $data['user']);
    return true;
  };
}, 'games');

// *** Slot machine

$bot->add_allymsg_hook("Slot",                      // command key
                       "LouBot_slot_maschine",      // callback function
                       true,                        // is a command PRE needet?
                       '/^slot$/i',                 // optional regex for key
function ($bot, $data) {
  global $redis;
  if(!$bot->is_himself($data['user'])) {
    $games_key = "games:alliance:{$bot->ally_id}";
    // get game values
    $start1 = $redis->GET("{$games_key}:slot:start1");
    $start2 = $redis->GET("{$games_key}:slot:start2");
    $start3 = $redis->GET("{$games_key}:slot:start3");
    $round = $redis->INCR("{$games_key}:slot:count");
    // setup game
    $faces = array ('Ο', '♣', '♣♣', '♣♣♣', '♥', '♠', '♠♠', '♠♠♠', '♦', '7');
    $payouts = array (
        '♣|♣|♣' => 1,
        '♣♣|♣♣|♣♣' => 3,
        '♣♣♣|♣♣♣|♣♣♣' => 7,
        '♠|♠|♠' => 5,
        '♠♠|♠♠|♠♠' => 10,
        '♠♠♠|♠♠♠|♠♠♠' => 15,
        '♥|♥|♥' => 20,
        '7|7|7' => 70,
        '♦|♦|♦' => 100,
    );
    $wheel1 = array();
    foreach ($faces as $face) {
        $wheel1[] = $face;
    }
    $wheel2 = array_reverse($wheel1);
    $wheel3 = $wheel1;
      
    $stop1 = rand(count($wheel1) + $start1, 10*count($wheel1)) % count($wheel1);
    $stop2 = rand(count($wheel2) + $start2, 10*count($wheel2)) % count($wheel2);
    $stop3 = rand(count($wheel3) + $start3, 10*count($wheel3)) % count($wheel3);
    /*
    shuffle($wheel1);
    shuffle($wheel2);
    shuffle($wheel3);
    
    $stop1 = array_rand($wheel1, 1) + $start1;
    $stop2 = array_rand($wheel2, 1) + $start2;
    $stop3 = array_rand($wheel3, 1) + $start3;
    */
    
    $redis->SET("{$games_key}:slot:start1", $stop1);
    $redis->SET("{$games_key}:slot:start2", $stop2);
    $redis->SET("{$games_key}:slot:start3", $stop3);

    $result1 = $wheel1[$stop1];
    $result2 = $wheel2[$stop2];
    $result3 = $wheel3[$stop3];

    $bot->add_allymsg("Slot maschine: {$result1}|{$result2}|{$result3}");
    if (isset($payouts["{$result1}|{$result2}|{$result3}"])) {
      $points = $payouts["{$result1}|{$result2}|{$result3}"];
      $redis->SET("{$games_key}:slot:lastwin", $round);
      $incr = $redis->ZINCRBY("{$games_key}:slot:points", $points, $data['user']);
      $total = $redis->ZINCRBY("points:alliance:{$bot->ally_id}", $points, $data['user']);
      $bot->add_allymsg("{$data['user']} hat {$points} Punkte gewonnen und insgesammt {$incr}/{$total} Punkte gesammelt!");
    }
  };
}, 'games');

// *** Hangman

define('HANGMAN', 'HANGMAN');
$bot->add_allymsg_hook("Hangman",                   // command key
                       "LouBot_hangman",            // callback function
                       false,                       // is a command PRE needet?
                       '/^[!]?hangman$/i',          // optional regex for key
function ($bot, $data) {
  global $redis, $hwords;
  if(!$bot->is_himself($data['user'])) {
    $commands = array('start', 'stop', 'pause');
    $games_key = "games:alliance:{$bot->ally_id}";
    if ($data['command'][0] == PRE && (in_array(strtolower($data['params'][0]), $commands) || empty($data['params'][0]))) {
      switch (strtolower($data['params'][0])) {
        case 'pause':
          if($bot->is_op_user($data['user'])) {
            $redis->SET("{$games_key}:hangman:pause", $data['user'], 360);
            if ($redis->GET("{$games_key}:running") == HANGMAN) {
              $redis->DEL("{$games_key}:running");
              return hangman_main($data['user'], null, true);
            }
          } else $bot->add_privmsg("Ne Ne Ne!", $data['user']); 
          break;
        case 'stop':
            if ($redis->GET("{$games_key}:running") != HANGMAN) return $bot->add_allymsg('Games Fehler: es läuft zur Zeit kein Spiel!');
            $redis->DEL("{$games_key}:running");
            return hangman_main($data['user'], null, true);
          break;
        case 'start':
        default:
          if ($redis->GET("{$games_key}:running")) $bot->add_privmsg('Games Fehler: Ein anderes Spiel läuft noch!', $data['user']);
          else if ($redis->ttl("{$games_key}:hangman:pause") !== -1) $bot->add_allymsg('Games Fehler: '.$redis->GET("{$games_key}:hangman:pause").' hat eine Pause gesetzt von 10 Min!');
          else {
            $letters = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
            $right = array_fill_keys($letters, '-');
            $wrong = array();
            $redis->SET("{$games_key}:running", HANGMAN);
            $redis->SET("{$games_key}:hangman:start", time());
            $redis->SET("{$games_key}:hangman:versuche", 0);
            $redis->DEL("{$games_key}:hangman:player");
            $redis->SADD("{$games_key}:hangman:player" , $data['user']);
            $redis->SET("{$games_key}:hangman:rightstr", serialize($right));
            $redis->SET("{$games_key}:hangman:wrongstr", serialize($wrong));
            if (empty($hwords)) {
              $lines = file(PERM_DATA.'hangman.' . BOT_LANG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
              foreach ($lines as $line_num => $line) {
                if ($line[0] != '#') $hwords[$line_num] = trim(strtoupper(htmlspecialchars($line)));
              }
            }
            shuffle($hwords);
            $rand_key = array_rand($hwords, 1);
            $word = $hwords[$rand_key];
            $redis->SET("{$games_key}:hangman:wort", $word);
            // start play hangman
            $show = '';
            $bot->add_allymsg('Games Hangman: neues Spiel gestartet!');
            $bot->add_allymsg('Hangman: ' . str_pad($show, strlen($word) , '-'));
          }
          break;
      }
    } else if($data['command'][0] != PRE && !empty($data['params'][0])) {
      // go play hangman
      if ($redis->GET("{$games_key}:running") == HANGMAN) return hangman_main($data['user'], strtoupper($data['params'][0]));
      else $bot->add_privmsg('Games Fehler: es läuft zur Zeit kein Spiel oder es wurde gerade beendet!', $data['user']);
    } else $bot->add_privmsg('Games Fehler: falsche Parameter!', $data['user']);
  };
}, 'fast_games');

if(!function_exists('hangman_main')) {
  function hangman_main($user = '', $guess = '', $force = false) {
    global $redis, $bot;
    $magic = 2.358;
    $games_key = "games:alliance:{$bot->ally_id}";
    $guess = trim(strtoupper($guess));
    $word = $redis->GET("{$games_key}:hangman:wort");
    $wrong_count = (strlen($word) > 6) ? strlen($word) : 6;
    $right = unserialize($redis->GET("{$games_key}:hangman:rightstr"));
    $wrong = unserialize($redis->GET("{$games_key}:hangman:wrongstr"));
    $wordletters = str_split($word);
    $show = '';
    if (stristr($word, $guess)) {
      if (in_array($guess, $right)) return $bot->add_privmsg("Hangman: [b]{$guess}[/b] war schon!", $data['user']);
      $right[$guess] = $guess;
      $redis->SADD("{$games_key}:hangman:player" , $data['user']);
      $redis->SET("{$games_key}:hangman:rightstr", serialize($right));
      $wordletters = str_split($word);
      foreach ($wordletters as $letter) {
        $show .= $right[$letter];
      }
      if ($guess == $word || $show == $word) {
        $redis->DEL("{$games_key}:running");
        $redis->SET("{$games_key}:hangman:lastwin", $user);
        $points = round(strlen($word) * $redis->SCARD("{$games_key}:hangman:player") * $magic / $redis->GET("{$games_key}:hangman:versuche"), 0);
        $incr = $redis->ZINCRBY("{$games_key}:hangman:points", $points, $user);
        $total = $redis->ZINCRBY("points:alliance:{$bot->ally_id}", $points, $user);
        $bot->add_allymsg("Games Hangman: wurde gelöst von {$user} ☻");
        $bot->add_allymsg("{$user} hat {$points} Punkte gewonnen und insgesammt {$incr}/{$total} Punkte gesammelt!");
        $bot->add_allymsg('Gesucht war: ' . $word);
        return true;
      } else $redis->INCR("{$games_key}:hangman:versuche");
    } else {
      if (in_array($guess, $wrong)) return $bot->add_privmsg("Hangman: [b]{$guess}[/b] war schon!", $user);
      $wrong[$guess] = $guess;
      if (count($wrong) >= $wrong_count || $force) {
        $redis->DEL("{$games_key}:running");
        $bot->add_allymsg("Games Hangman: wurde nicht gelöst ☹");
        $bot->add_allymsg('Gesucht war: ' . $word);
        return true;
      } else {
        $redis->INCR("{$games_key}:hangman:versuche");
        $redis->SADD("{$games_key}:hangman:player" , $data['user']);
        $redis->SET("{$games_key}:hangman:wrongstr", serialize($wrong));
        foreach ($wordletters as $letter) {
          $show .= $right[$letter];
        }
      }
    }
    $bot->add_allymsg('Versuche ('.$redis->GET("{$games_key}:hangman:versuche").'): ' . implode(', ', $wrong));
    $bot->add_allymsg('Hangman: ' . $show);
    return true;
  }
}

// *** Quizzer

define('QUIZZER', 'QUIZZER');
$bot->add_allymsg_hook("Quiz",                   // command key
                       "LouBot_quiz",            // callback function
                       false,                    // is a command PRE needet?
                       '/^[!]?(quiz|quizzer)$/i',           // optional regex for key
function ($bot, $data) {
  global $redis, $questions;
  if(!$bot->is_himself($data['user'])) {
    $commands = array('start', 'stop', 'reload', 'pause');
    $games_key = "games:alliance:{$bot->ally_id}";
    if ($data['command'][0] == PRE && (in_array(strtolower($data['params'][0]), $commands) || empty($data['params'][0]))) {
      switch (strtolower($data['params'][0])) {
        case 'pause':
          if($bot->is_op_user($data['user'])) {
            $redis->SET("{$games_key}:quizzer:pause", $data['user'], 360);
            if ($redis->GET("{$games_key}:running") == QUIZZER) {
              $redis->DEL("{$games_key}:running");
              return quizzer_main4(null, null, true);
            }
          } else $bot->add_privmsg("Ne Ne Ne!", $data['user']); 
          break;
        case 'reload':
          if($bot->is_op_user($data['user'])) unset($questions);
          else $bot->add_privmsg("Ne Ne Ne!", $data['user']); 
          break;
        case 'stop':
            if ($redis->GET("{$games_key}:running") != QUIZZER) return $bot->add_allymsg('Games Fehler: es läuft zur Zeit kein Spiel!');
            $redis->DEL("{$games_key}:running");
            return quizzer_main4(null, null, true);
          break;
        case 'start':
        default:
          if ($redis->GET("{$games_key}:running")) $bot->add_privmsg('Games Fehler: Ein anderes Spiel läuft noch!', $data['user']);
          else if ($redis->ttl("{$games_key}:quizzer:pause") !== -1) $bot->add_allymsg('Games Fehler: '.$redis->GET("{$games_key}:quizzer:pause").' hat eine Pause gesetzt von 10 Min!');
          else {
            if (empty($questions)) {
              #  Category?                              (should always be on top!)
              #  Question                               (should always stand after Category)
              #  Answer                                 (will be matched if no regexp is provided)
              #  Regexp?                                (use UNIX-style expressions)
              #  Author?                                (the brain behind this question)
              #  Level? [baby|easy|normal|hard|extreme] (difficulty)
              #  Comment?                               (comment line)
              #  Score? [#]                             (credits for answering this question)
              #  Tip*                                   (provide one or more hints)
              #  TipCycle? #                            (Specify number of generated tips)
              $tags = array('CATEGORY', 'QUESTION', 'ANSWER', 'REGEXP', 'AUTHOR', 'LEVEL', 'COMMENT', 'SCORE', 'TIP', 'TIPCYCLE');
              $lines = file(PERM_DATA.'questions.' . BOT_LANG, FILE_IGNORE_NEW_LINES);
              $levels = array('baby' => 3,'easy' => 5,'normal' => 15,'hard' => 20,'extreme' => 25);
              $i = 0;
              foreach ($lines as $line_num => $line) {
                if ($line[0] == '#') continue;
                if (trim($line) == '') {
                  if (empty($data[$i]['QUESTION'])) continue;
                  else {
                    $questions[$i] = array(
                      'CATEGORY' => (empty($data[$i]['CATEGORY'][0])) ? 'Allgemein' : $data[$i]['CATEGORY'][0],
                      'QUESTION' => $data[$i]['QUESTION'][0],
                      'ANSWER'   => (strpos($data[$i]['ANSWER'][0], '#') === false) ? $data[$i]['ANSWER'][0] : preg_replace('/#(.*)#/i', '[i]$1[/i]', $data[$i]['ANSWER'][0]),
                      'REGEXP'   => (empty($data[$i]['REGEXP'][0])) ? generate_regexp($data[$i]['ANSWER'][0]) : generate_regexp($data[$i]['REGEXP'][0]),
                      'AUTHOR'   => (empty($data[$i]['AUTHOR'][0])) ? '' : $data[$i]['AUTHOR'][0],
                      'LEVEL'    => (empty($data[$i]['LEVEL'][0])) ? 'normal' : $data[$i]['LEVEL'][0],
                      'COMMENT'  => (empty($data[$i]['COMMENT'][0])) ? '' : $data[$i]['COMMENT'][0],
                      'SCORE'    => (empty($data[$i]['SCORE'][0])) ? ((empty($data[$i]['LEVEL'][0])) ? $levels['normal'] : $levels[$data[$i]['LEVEL'][0]]) : $levels[$data[$i]['SCORE'][0]],
                      'TIP'      => (empty($data[$i]['TIP'])) ? array('kein Tipp') : $data[$i]['TIP'],
                      'TIPCYCLE' => (empty($data[$i]['TIPCYCLE'][0])) ? count($data[$i]['TIP']) : $data[$i]['TIPCYCLE'][0],
                      'SOLVED'   => false
                    );
                    $i++;
                    continue;
                  }
                }
                list($tag, $text) = explode(':', $line, 2);
                $data[$i][trim(strtoupper($tag))][] = trim($text);
              } unset($data);
            } if (empty($questions)) return $bot->add_allymsg('Games Fehler: keine Fragen geladen!');
            do {
              shuffle($questions);
              $rand_key = array_rand($questions, 1);
              $question = $questions[$rand_key];
            } while ($question['SOLVED']);
            $wrong = array();
            $redis->SET("{$games_key}:running", QUIZZER);
            $redis->SET("{$games_key}:quizzer:start", time());
            $redis->SET("{$games_key}:quizzer:versuche", 0);
            $redis->DEL("{$games_key}:quizzer:player");
            $redis->SADD("{$games_key}:quizzer:player" , $data['user']);
            $redis->SET("{$games_key}:quizzer:wrongstr", serialize($wrong));
            $redis->SET("{$games_key}:quizzer:question", $rand_key);
            // start play quizzer
            $category = ucfirst($question['CATEGORY']);
            $bot->add_allymsg("Games Quiz: neues Spiel gestartet ({$question['LEVEL']}) - [i]{$category}[/i]");
            $bot->add_allymsg("Frage: [i]{$question['QUESTION']}[/i]");
          }
          break;
      }
    } else if($data['command'][0] != PRE && !empty($data['message'])) {
      // go play quizzer
      if ($redis->GET("{$games_key}:running") == QUIZZER) return quizzer_main4($data['user'], strtolower(implode(' ' , $data['params'])));
      else $bot->add_privmsg('Games Fehler: es läuft zur Zeit kein Spiel oder es wurde gerade beendet!', $data['user']);
    } else $bot->add_privmsg('Games Fehler: falsche Parameter!', $data['user']);
  };
}, 'fast_games');

if(!function_exists('quizzer_main4')) {
  function quizzer_main4($user = '', $guess = '', $force = false) {
    global $redis, $bot, $questions;
    $magic = 2.358;
    $games_key = "games:alliance:{$bot->ally_id}";
    $guess = trim(strtoupper($guess));
    $question = $redis->GET("{$games_key}:quizzer:question");
    $wrong = unserialize($redis->GET("{$games_key}:quizzer:wrongstr"));
    $wrong_count = 6;
    $bot->log('Quiz:' . $questions[$question]['REGEXP'] . ' | ' . $guess);
    $regex = "/^{$questions[$question]['REGEXP']}$/i";
    $time_to_solve = time() - $redis->GET("{$games_key}:quizzer:start");
    $redis->SADD("{$games_key}:quizzer:player" , $data['user']);
    if (preg_match($regex, $guess) && !$force) {
      $redis->DEL("{$games_key}:running");
      $redis->SET("{$games_key}:quizzer:lastwin", $user);
      $questions[$question]['SOLVED'] = true;
      $chance = round($time_to_solve / 10, 0);
      //$points = round(($questions[$question]['SCORE'] - $questions[$question]['SCORE'] / 10 * $chance) * ($redis->GET("{$games_key}:quizzer:versuche") / $redis->SCARD("{$games_key}:quizzer:player")), 0);
      $points = $questions[$question]['SCORE'] - round($questions[$question]['SCORE']/7*$chance);
      $minimum_points = round($questions[$question]['SCORE']/7);
      $points = ($points <= 0) ? (($minimum_points <= 0) ? 1 : $minimum_points) : $points;
      $incr = $redis->ZINCRBY("{$games_key}:quizzer:points", $points, $user);
      $total = $redis->ZINCRBY("points:alliance:{$bot->ally_id}", $points, $user);
      $bot->add_allymsg("Games Quiz: wurde gelöst von {$user} ☻");
      $bot->add_allymsg("{$user} hat {$points} Punkte gewonnen und insgesammt {$incr}/{$total} Punkte gesammelt!");
      $bot->add_allymsg('Gesucht war: ' . $questions[$question]['ANSWER']);
      return true;
    } else {
      if (in_array($guess, $wrong) && !$force) return $bot->add_privmsg("Quiz: [b]{$guess}[/b] war schon!", $user);
      $wrong[$guess] = $guess;
      if (count($wrong) >= $wrong_count || $force) {
        $redis->DEL("{$games_key}:running");
        $bot->add_allymsg("Games Quiz: wurde nicht gelöst ☹");
        $bot->add_allymsg('Gesucht war: ' . $questions[$question]['ANSWER']);
        return true;
      } else {
        $redis->INCR("{$games_key}:quizzer:versuche");
        $redis->SET("{$games_key}:quizzer:wrongstr", serialize($wrong));
        // tipps?
      }
    }
    $bot->add_allymsg('Versuche ('.$redis->GET("{$games_key}:quizzer:versuche").'): ' . implode(', ', $wrong));
    return true;
  }
}

if(!function_exists('generate_regexp')) {
  function generate_regexp($string) {
    preg_match_all('/(.*)#(.*)#(.*)/i', strtolower($string), $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $regex = "(";
      if ($matches[0][1] != '') $regex .= "(" . $matches[0][1] . ")?";
      $regex .= "(" . $matches[0][2] . ")";
      if ($matches[0][3] != '') $regex .= "(" . $matches[0][3] . ")?";
      $regex .= ")";
    } else {
      $regex = strtolower($string);
    }
    $regex = str_replace('ä', '(ä|ae)', $regex);
    $regex = str_replace('ü', '(ü|ue)', $regex);
    $regex = str_replace('ö', '(ö|oe)', $regex);
    $regex = str_replace('ß', '(ß|ss|s)', $regex);
    return $regex;
  }
}

?>