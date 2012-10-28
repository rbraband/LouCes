<?php
global $bot;
$bot->add_category('email', array(), PUBLICY);

// crons
$bot->add_tick_event(Cron::TICK1,                 // Cron key
                    "GetEMAILUpdate",             // command key
                    "LouBot_email_update_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $inbound = $redis->SMEMBERS("email:inbound");
  if (is_array($inbound)) foreach($inbound as $key) {
    $email = $redis->HGETALL("email:receive:{$key}");
    $bot->log("New inbound EMAIL ({$key})");
    $redis->SREM("email:inbound", $key);
    $sender = $bot->get_user_name_by_id($email['sender']);
    $receiver = $bot->get_user_name_by_id($email['receiver']);
    if (strpos((string)$email['message'], ALLYIN) === 0 ) {
      $email['message'] = substr((string)$email['message'], strlen(ALLYIN));
      $message = "EMAIL-Nachricht von [i]{$sender}[/i]: " . $email['message'];
      $bot->add_allymsg($message);
    } else if (strpos((string)$email['message'], PRIVATEIN) === 0 ) {
      $email['message'] = substr((string)$email['message'], strlen(PRIVATEIN));
      $message = "EMAIL-Nachricht von [i]{$sender}[/i]: " . $email['message'];
      $bot->add_privmsg($message, $receiver);
    } else {
      $message = "EMAIL-Nachricht von [i]{$sender}[/i]: " . $email['message'];
      switch($email['returnto']) {
        case ALLYIN:
          $bot->add_allymsg($message);
          break;
        default:
          $bot->add_privmsg($message, $receiver);
      }
    }
  }
  $outbound = $redis->SMEMBERS("email:outbound");
  if (is_array($outbound)) foreach($outbound as $key) {
    $message = false;
    $email = $redis->HGETALL("email:send:{$key}");
    $bot->log("Update EMAIL ({$key})");
    $redis->SREM("email:outbound", $key);
    $sender = $bot->get_user_name_by_id($email['sender']);
    $receiver = $bot->get_user_name_by_id($email['receiver']);
    switch ($email['status']) {
      case EMAIL_STATUS_ANSWERED:
        $message = "Deine EMAIL an {$receiver} wurde beantwortet.";
        break;
      case EMAIL_STATUS_TRANSMITTED:
        $message = "Deine EMAIL an {$receiver} wurde übertragen.";
        break;
      case EMAIL_STATUS_BUFFERED:
        $message = "Deine EMAIL an {$receiver} wurde zwischengespeichert, {$receiver} ist zur Zeit nicht erreichbar.";
        break;
      default:
        continue;
    }
    if ($message) $bot->add_privmsg($message, $sender);
  }
  return true;
}, 'email');

$bot->add_tick_event(Cron::TICK1,                       // Cron key
                    "GetEMAILAlertUpdate",              // command key
                    "LouBot_alert_email_update_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $alliance_key = "alliance:{$bot->ally_id}";
  $attacks = $redis->getKeys("attacks:{$alliance_key}:[0-9]*");
  if (is_array($attacks)) foreach($attacks as $attack) {
    $att_key = $redis->clearKey($attack, '/attacks:/');
    
  }
  return true;
}, 'email');


// hooks
$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
									 "EMAIL",                 // command key
									 "LouBot_email",          // callback function
									 false,                   // is a command PRE needet?
									 '/^[!]?E?MAIL$/i',       // optional regex for key
function ($bot, $data) {
  global $redis, $mail;
  if (!$redis->status()) return;
  $commands = array('setaddr', 'addr', 'alarm', 'erlaube', 'multi');
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    if (in_array(strtolower($data['params'][0]), $commands)) {
      switch (strtolower($data['params'][0])) {
        case 'setaddr':
          if ($bot->is_op_user($data['user']) && $data['command'][0] == PRE) {
            if (!filter_var(mb_strtolower(trim($data['params'][1])), FILTER_VALIDATE_EMAIL)) {
              $message = 'Die Adresse [i]' . mb_strtolower(trim($data['params'][1])) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            if (!$bot->is_ally_user($data['params'][2])) {
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg('Der Alias [i]' . ucfirst(mb_strtolower($data['params'][2])) . "[/i] ist nicht belegt!");
              else 
                $bot->add_privmsg('Der Alias [i]' . ucfirst(mb_strtolower($data['params'][2])) . "[/i] ist nicht belegt!", $data['user']);
              return true;
            }
            $uid = $bot->get_user_id($data['params'][2]);
            $addr = mb_strtolower(trim($data['params'][1]));
            $insert = $redis->HMSET("email", array(
              $addr => $uid
            ));
            if ($insert) {// prüfen ob multi
              $redis->HMSET("user:{$uid}:email", array(
                'addr'      => $addr,
                'allow'     => EMAIL_ALL,
                'alert'     => EMAIL_ALERT_OWN,
                'share'     => EMAIL_SHARE_OFF
              ));
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($data['params'][2] . "'s Adresse [i]{$addr}[/i] gespeichert.");
              else 
                $bot->add_privmsg($data['params'][2] . "'s Adresse [i]{$addr}[/i] ist gespeichert.", $data['user']);        
              return true;
            } else {
              $uid = $redis->HGET('email', mb_strtolower($data['params'][1]));
              $nick = $redis->HGET("user:{$uid}:data", 'name');
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Adresse [i]{$addr}[/i] ist belegt!");
              else 
                $bot->add_privmsg("Die Adresse [i]{$addr}[/i] ist ".(($nick == $data['user']) ? 'von Dir ' : '')."belegt!", $data['user']);
              return true;
            }
          } else {
            $bot->add_privmsg('EMAIL Fehler: falsche Parameter!', $data['user']);
            return true;
          }
          break;
        case 'addr':
          if ($data['command'][0] == PRE) {
            if (!filter_var(mb_strtolower(trim($data['params'][1])), FILTER_VALIDATE_EMAIL)) {
              $message = 'Die Adresse [i]' . mb_strtolower(trim($data['params'][1])) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $uid = $bot->get_user_id($data['user']);
            $addr = mb_strtolower(trim($data['params'][1]));
            $insert = $redis->HMSET("email", array(
              $addr => $uid
            ));
            if ($insert) {// prüfen ob multi
              $redis->HMSET("user:{$uid}:email", array(
                'addr'      => $addr,
                'allow'     => EMAIL_ALL,
                'alert'     => EMAIL_ALERT_OWN,
                'share'     => EMAIL_SHARE_OFF
              ));
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("{$data['user']}'s Adresse [i]{$addr}[/i] gespeichert.");
              else 
                $bot->add_privmsg("Deine Adresse [i]{$addr}[/i] ist gespeichert.", $data['user']);        
              return true;
            } else {
              $uid = $redis->HGET('email', mb_strtolower($data['params'][1]));
              $nick = $redis->HGET("user:{$uid}:data", 'name');
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Adresse [i]{$addr}[/i] ist belegt!");
              else 
                $bot->add_privmsg("Die Adresse [i]{$addr}[/i] ist ".(($nick == $data['user']) ? 'von Dir ' : '')."belegt!", $data['user']);
              return true;
            }
          } else {
            if (!filter_var(mb_strtolower(trim($data['params'][1])), FILTER_VALIDATE_EMAIL)) {
              $message = 'Die Adresse [i]' . mb_strtolower($data['params'][1]) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $addr = mb_strtolower($data['params'][1]);
            $uid = $redis->HGET('email', $addr);
            if ($uid) {
              $nick = $redis->HGET("user:{$uid}:data", 'name');
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Adresse [i]{$addr}[/i] ist belegt!");
              else 
                $bot->add_privmsg("Die Adresse [i]{$addr}[/i] ist ".(($nick == $data['user']) ? 'von Dir ' : '')."belegt!", $data['user']);
              return true;
            } else {
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Adresse [i]{$addr}[/i] ist nicht belegt!");
              else 
                $bot->add_privmsg("Die Adresse [i]{$addr}[/i] ist nicht belegt!", $data['user']);
              return true;
            }
          }
          break;
        case 'alarm':
          $uid = $bot->get_user_id($data['user']);
          if ($data['command'][0] == PRE) {
            if (!preg_match('/^(on|off|an|aus|ein|ja|nein|1|0|alle|ally|alliance|alli)$/i', mb_strtolower($data['params'][1]), $match)) {
              $message = 'Die Eingabe [i]' . mb_strtolower($data['params'][1]) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $set = array('on'   => EMAIL_ALERT_OWN, 
                         'off'  => EMAIL_ALERT_OFF, 
                         'an'   => EMAIL_ALERT_OWN, 
                         'aus'  => EMAIL_ALERT_OFF, 
                         'ein'  => EMAIL_ALERT_OWN, 
                         'ja'   => EMAIL_ALERT_OWN, 
                         'nein' => EMAIL_ALERT_OFF, 
                         '1'    => EMAIL_ALERT_OWN, 
                         '0'    => EMAIL_ALERT_OFF, 
                         'alle' => EMAIL_ALERT_ALL, 
                         'all'  => EMAIL_ALERT_ALL, 
                         'alli' => EMAIL_ALERT_ALL, 
                         'ally' => EMAIL_ALERT_ALL, 
                         'alliance' => EMAIL_ALERT_ALL
            );
            $redis->HMSET("user:{$uid}:email", array(
              'alert' => $set[mb_strtolower($data['params'][1])]
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s Eingabe wurde gespeichert.");
            else 
              $bot->add_privmsg("Deine Eingabe wurde gespeichert.", $data['user']);        
            return true;
          } else {
            $alarm = $redis->HGET("user:{$uid}:email", 'alert');
            switch ($alarm) {
              case EMAIL_ALERT_OWN:
                $setting = 'Alarm = nur eigene';
                break;
              case EMAIL_ALERT_ALL:
                $setting = 'Alarm = alle';
                break;
              case EMAIL_ALERT_OFF:
                default:
                $setting = 'Alarm = aus';
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s EMAIL-Einstellung: " . $setting);
            else 
              $bot->add_privmsg("Deine EMAIL-Einstellung: " . $setting, $data['user']);        
            return true;
          }
          break;
        case 'multi':
          $uid = $bot->get_user_id($data['user']);
          if ($data['command'][0] == PRE) {
            if (!preg_match('/^(on|off|an|aus|ein|ja|nein|1|0)$/i', mb_strtolower($data['params'][1]), $match)) {
              $message = 'Die Eingabe [i]' . mb_strtolower($data['params'][1]) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $set = array('on'   => EMAIL_SHARE_ON,
                         'off'  => EMAIL_SHARE_OFF, 
                         'an'   => EMAIL_SHARE_ON, 
                         'aus'  => EMAIL_SHARE_OFF, 
                         'ein'  => EMAIL_SHARE_ON, 
                         'ja'   => EMAIL_SHARE_ON, 
                         'nein' => EMAIL_SHARE_OFF, 
                         '1'    => EMAIL_SHARE_ON, 
                         '0'    => EMAIL_SHARE_OFF
            );
            $redis->HMSET("user:{$uid}:email", array(
              'share' => $set[mb_strtolower($data['params'][1])]
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s Eingabe wurde gespeichert.");
            else 
              $bot->add_privmsg("Deine Eingabe wurde gespeichert.", $data['user']);        
            return true;
          } else {
            $multi = $redis->HGET("user:{$uid}:email", 'multi');
            switch ($multi) {
              case EMAIL_SHARE_ON:
                $setting = 'Multi = ein';
                break;
              case EMAIL_SHARE_OFF:
                default:
                $setting = 'Multi = aus';
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s EMAIL-Einstellung: " . $setting);
            else 
              $bot->add_privmsg("Deine EMAIL-Einstellung: " . $setting, $data['user']);        
            return true;
          }
          break;
        case 'erlaube':
          $uid = $bot->get_user_id($data['user']);
          if ($data['command'][0] == PRE) {
            if (!preg_match('/^(all|alle|offiziere|offz|offi|leitung|lead|leader|führung|alarm)$/i', mb_strtolower($data['params'][1]), $match)) {
              $message = 'Die Eingabe [i]' . mb_strtolower($data['params'][1]) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $set = array('all'        => EMAIL_ALL, 
                         'alle'       => EMAIL_ALL, 
                         'offiziere'  => EMAIL_OFF, 
                         'offz'       => EMAIL_OFF, 
                         'offi'       => EMAIL_OFF, 
                         'leitung'    => EMAIL_LEAD, 
                         'leader'     => EMAIL_LEAD, 
                         'lead'       => EMAIL_LEAD, 
                         'alarm'      => EMAIL_SYS,
                         'führung'    => EMAIL_LEAD
            );
            $redis->HMSET("user:{$uid}:email", array(
              'allow' => $set[mb_strtolower($data['params'][1])]
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s Eingabe wurde gespeichert.");
            else 
              $bot->add_privmsg("Deine Eingabe wurde gespeichert.", $data['user']);        
            return true;
          } else {
            $allow = $redis->HGET("user:{$uid}:email", 'allow');
            switch ($allow) {
              case EMAIL_ALL:
                $setting = 'Erlaubnis = alle';
                break;
              case EMAIL_OFF:
                $setting = 'Erlaubnis = Führung & Offiziere';
                break;
              case EMAIL_LEAD:
                $setting = 'Erlaubnis = Führung';
                break;
              case EMAIL_SYS:
                default:
                $setting = 'Erlaubnis = nur Alarm';
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s EMAIL-Einstellung: " . $setting);
            else 
              $bot->add_privmsg("Deine EMAIL-Einstellung: " . $setting, $data['user']);        
            return true;
          }
          break;
      }
    } else if (!$bot->is_ally_user($data['params'][0])) {
      if ($data["channel"] == ALLYIN)
        $bot->add_allymsg('Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist nicht belegt!");
      else 
        $bot->add_privmsg('Der Alias [i]' . ucfirst(mb_strtolower($data['params'][0])) . "[/i] ist nicht belegt!", $data['user']);
      return true;
    } else if ($data['command'][0] == PRE) {
      $uid = $bot->get_user_id($data['user']);
      $nick = array_shift($data['params']);
      $tuid = $bot->get_user_id($nick);
      $toaddr = $redis->HGET("user:{$tuid}:email", 'addr');
      $spam = "email:spamcheck:{$uid}";
      $date = date('dmy');
      $email_count = "email:count";
      $email_send = "email:send";
      if ($redis->TTL($spam) === -1) {
        $bot->log("EMAILnoSPAM");
        if (!$toaddr) {
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg("{$data['user']}, {$nick} hat die EMAIL-Funktion nicht aktiviert!");
          else 
            $bot->add_privmsg("{$nick} hat die EMAIL-Funktion nicht aktiviert!", $data['user']);
          return true;
        }
        $allow = $redis->HGET("user:{$tuid}:email", 'allow');
        $role = $redis->HGET("user:{$uid}:data", 'role');          
        $access = $bot->get_access($data['user'], $allow);
        if ($access) {
          $message = implode(' ', $data['params']);
          $result = $mail->sendEMAIL($toaddr, $message, $subject = "~".$bot->ally_name."~ ".(($data["channel"] != ALLYIN)? 'private ':'')."Nachricht von {$data['user']}", $from = $bot->bot_user_name);
          
          $redis->SET($spam, 0, EMAIL_SPAMTTL);
          if ($result['error'] === true) $return = "Fehler bei der übermittlung: {$result['text']} ({$result['code']})";
          
          else {
            $all_count = $redis->INCR($email_count);
            $redis->HINCRBY("user:{$uid}:email", 'email', 1);
            $redis->HMSET("{$email_send}:{$result['id']}", array(
              'returnto'  => $data["channel"],
              'status'    => EMAIL_STATUS_OPEN,
              'sender'    => $uid,
              'receiver'  => $tuid,
              'datetime'  => time(),
              'result'    => $result['code']));
            $return = $result['text'];
            $bot->log("Email versendet: {$all_count}");
          }
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg("{$data['user']}, {$return}");
          else 
            $bot->add_privmsg($return, $data['user']);
          return true;
        } else {
          $_role = $bot->get_role($role);
          if ($data["channel"] == ALLYIN)
            $bot->add_allymsg("{$data['user']}, {$nick}'s Einstellungen lassen keine Email von dir als {$_role} zu!");
          else 
            $bot->add_privmsg("{$nick}'s Einstellungen lassen keine Email von dir als {$_role} zu!", $data['user']);
          return true;
        }
      } else {
        $incr = $redis->INCR($spam) * EMAIL_SPAMTTL;
        $bot->add_privmsg("EMAIL-SpamCheck! ({$incr} sec.)", $data['user']);
        $redis->EXPIRE($spam, $incr);
        return true;
      }  
    }
    $bot->add_privmsg('EMAIL Fehler: falsche Parameter!', $data['user']);

	} else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'email');                   
?>