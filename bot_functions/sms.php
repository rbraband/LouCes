<?php
global $bot;
$bot->add_category('sms', array(), PUBLICY);

// crons
$bot->add_tick_event(Cron::TICK1,               // Cron key
                    "GetSMSUpdate",             // command key
                    "LouBot_sms_update_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $inbound = $redis->SMEMBERS("sms:inbound");
  if (is_array($inbound)) foreach($inbound as $key) {
    $sms = $redis->HGETALL("sms:receive:{$key}");
    $bot->log("New inbound SMS ({$key})");
    $redis->SREM("sms:inbound", $key);
    $sender = $bot->get_user_name_by_id($sms['sender']);
    $receiver = $bot->get_user_name_by_id($sms['receiver']);
    if (strpos((string)$sms['message'], ALLYIN) === 0 ) {
      $sms['message'] = substr((string)$sms['message'], strlen(ALLYIN));
      $message = "SMS-Nachricht von [i]{$sender}[/i]: " . $sms['message'];
      $bot->add_allymsg($message);
    } else if (strpos((string)$sms['message'], PRIVATEIN) === 0 ) {
      $sms['message'] = substr((string)$sms['message'], strlen(PRIVATEIN));
      $message = "SMS-Nachricht von [i]{$sender}[/i]: " . $sms['message'];
      $bot->add_privmsg($message, $receiver);
    } else {
      $message = "SMS-Nachricht von [i]{$sender}[/i]: " . $sms['message'];
      switch($sms['returnto']) {
        case ALLYIN:
          $bot->add_allymsg($message);
          break;
        default:
          $bot->add_privmsg($message, $receiver);
      }
    }
  }
  $outbound = $redis->SMEMBERS("sms:outbound");
  if (is_array($outbound)) foreach($outbound as $key) {
    $message = false;
    $sms = $redis->HGETALL("sms:send:{$key}");
    $bot->log("Update SMS ({$key})");
    $redis->SREM("sms:outbound", $key);
    $sender = $bot->get_user_name_by_id($sms['sender']);
    $receiver = $bot->get_user_name_by_id($sms['receiver']);
    switch ($sms['status']) {
      case SMS_STATUS_ANSWERED:
        $message = "Deine SMS an {$receiver} wurde beantwortet.";
        break;
      case SMS_STATUS_DELIVERED:
        $message = "Deine SMS an {$receiver} wurde zugestellt.";
        break;
      case SMS_STATUS_TRANSMITTED:
        $message = "Deine SMS an {$receiver} wurde übertragen.";
        break;
      case SMS_STATUS_BUFFERED:
        $message = "Deine SMS an {$receiver} wurde zwischengespeichert, {$receiver} ist zur Zeit nicht erreichbar.";
        break;
      case SMS_STATUS_NOT_DELIVERED:
        $message = "Deine SMS an {$receiver} konnte nicht zugestellt werden!";
        break;
      default:
        continue;
    }
    if ($message) $bot->add_privmsg($message, $sender);
  }
  return true;
}, 'sms');

$bot->add_tick_event(Cron::TICK1,                 // Cron key
                    "GetAlertUpdate",             // command key
                    "LouBot_alert_update_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $alliance_key = "alliance:{$bot->ally_id}";
  $attacks = $redis->getKeys("attacks:{$alliance_key}:[0-9]*");
  if (is_array($attacks)) foreach($attacks as $attack) {
    $att_key = $redis->clearKey($attack, '/attacks:/');
    
  }
  return true;
}, 'sms');

$bot->add_tick_event(Cron::DAILY,                 // Cron key
                    "DeleteDailyKontingents",     // command key
                    "LouBot_delete_daily_kontingents_cron",   // callback function
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  $count = 0;
  $date = date('dmy');
  $kontingents = $redis->getKeys('sms:kontingent:[0-9]*');
  if (is_array($kontingents)) foreach($kontingents as $kontingent) {
    //0   1          2      3
    //sms:kontingent:120811:7228
    $_keys = explode(':', $kontingent);
    if ($_keys[2] < $date) {
      $count++;
      $redis->DEL($kontingent);
    }
  }
  if ($count > 0) $bot->log("Old SMS Kontingents deleted ({$count})");
  return true;
}, 'sms');


// hooks
$bot->add_msg_hook(array(PRIVATEIN, ALLYIN),
                   "SMS",                 // command key
                   "LouBot_sms",          // callback function
                   false,                 // is a command PRE needet?
                   '/^[!]?SMS$/i',        // optional regex for key
function ($bot, $data) {
  global $redis, $sms;
  $region_codes = array(1,7,20,27,30,31,32,33,34,36,39,40,41,43,44,45,46,47,48,49,51,52,53,54,55,56,57,58,60,61,62,63,64,65,66,77,81,82,84,86,90,91,92,93,94,95,98,212,213,216,218,220,221,222,223,224,225,226,227,228,229,230,231,232,233,234,235,236,237,238,240,241,242,243,244,245,248,249,250,251,252,253,254,255,256,257,258,260,261,262,263,264,265,266,267,268,269,291,297,298,299,350,351,352,353,354,355,356,357,358,359,370,371,372,373,374,375,376,377,378,380,381,382,385,386,387,389,420,421,423,500,501,502,503,504,505,506,507,509,590,591,592,593,594,595,596,597,598,599,670,673,675,676,677,678,679,682,687,689,850,852,853,855,856,880,886,960,961,962,963,964,965,966,967,968,970,971,972,973,974,975,976,977,992,993,994,995,996,998,1784);
  if (!$redis->status()) return;
  $commands = array('setnr', 'nr', 'alarm', 'erlaube', 'multi', 'chat', 'region');
  if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
    if (in_array(strtolower($data['params'][0]), $commands)) {
      switch (strtolower($data['params'][0])) {
        case 'region':
          if ($data['command'][0] == PRE) {
            if (!in_array(intval(trim($data['params'][1])), $region_codes)) {
              $message = 'Die Region [i]' . mb_strtolower(trim($data['params'][1])) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $uid = $bot->get_user_id($data['user']);
            $region = intval(trim($data['params'][1]));
            $redis->HMSET("user:{$uid}:sms", array(
              'region'    => $region,
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s SMS-Region: " . $region);
            else 
              $bot->add_privmsg("Deine SMS-Region: " . $region, $data['user']);
            return true;
          } else {
            if (!($region = $redis->HGET("user:{$uid}:sms", 'region'))) {
              $region = SMS_REGION;
              $redis->HSET("user:{$uid}:sms", 'region', $region);
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s SMS-Region: " . $region);
            else 
              $bot->add_privmsg("Deine SMS-Region: " . $region, $data['user']);        
            return true;
          }  
          break;
        case 'chat':
          $uid = $bot->bot_user_id;
          $tuid = $bot->get_user_id($data['user']);
          $tonr = $redis->HGET("user:{$tuid}:sms", 'number');
          if (!($region = $redis->HGET("user:{$tuid}:sms", 'region'))) {
            $region = SMS_REGION;
            $redis->HSET("user:{$tuid}:sms", 'region', $region);
          }
          $date = date('dmy');
          $kontingent = "sms:kontingent:{$date}:{$tuid}";
          $sms_count = "sms:count";
          $sms_costs = "sms:costs";
          $kontingent_ttl = mktime(0, 0, 0, date("m")  , date("d")+1, date("Y")) - time();
          if ($redis->SETNX($kontingent, SMS_KONTINGENT)) $redis->EXPIRE($kontingent, $kontingent_ttl);
          $count = $redis->GET($kontingent);
          if($count && $count >= 1) {
            if (!$tonr) {
              $bot->add_privmsg('Du musst erst deine SMS Nr speichern!', $data['user']);
            } elseif($region != SMS_REGION) {
              $bot->add_privmsg('Chat funktioniert leider nur in Deutschland!', $data['user']);
            } else {
              $message = "~".$bot->ally_name."~\nChat-Einladung:\nAntworte einfach auf diese SMS und deine Antwort erscheint im Ally-CHAT!";
              $result = $sms->sendSMS($tonr, $message);
              if ($result['error'] === true) $return = "Fehler bei der übermittlung: {$result['text']} ({$result['code']})";
              else {
                $all_count = $redis->INCR($sms_count);
                $all_costs = $redis->INCRBY($sms_costs, $result['cost']*100);
                $count = $redis->DECR($kontingent);
                $redis->HINCRBY("user:{$tuid}:sms", 'sms', 1);
                $redis->HINCRBY("user:{$tuid}:sms", 'cost', $result['cost']*100);
                $redis->HMSET("sms:send:{$result['id']}", array(
                  'returnto'  => ALLYIN,
                  'status'    => SMS_STATUS_OPEN,
                  'sender'    => $uid,
                  'receiver'  => $tuid,
                  'datetime'  => time(),
                  'result'    => $result['code'],
                  'cost'      => $result['cost']*100));
                $return = "{$result['text']} ({$count}/".SMS_KONTINGENT.")";
                $redis->EXPIRE($kontingent, $kontingent_ttl);
                $bot->log("SMS versendet: {$all_count}/".number_format($all_costs/100, 2, ',', '' ));
              }
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("{$data['user']}, {$return}");
              else 
                $bot->add_privmsg($return, $data['user']);
              return true;
            }
          } else if ($count <= 0) {
            $bot->add_privmsg("SMS-Kontingent verbraucht!", $data['user']);
            return true;
          }
          return true;
          break;
        case 'setnr':
          if ($bot->is_op_user($data['user']) && $data['command'][0] == PRE) {
            if (!preg_match('/^(0(1|2|3|4|5|6|7|8|9)+[0-9]+[0-9]{7,9})$/', trim($data['params'][1]), $match)) {
              $message = 'Die Nr [i]' . mb_strtolower(trim($data['params'][1])) . '[/i] ist ungültig!';
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
            $nr = strval(trim($match[1]));
            $insert = $redis->HMSET("handy", array(
              $nr => $uid
            ));
            if ($insert) {// prüfen ob multi
              $redis->HMSET("user:{$uid}:sms", array(
                'number'    => $nr,
                'region'    => SMS_REGION,
                'allow'     => SMS_ALL,
                'alert'     => SMS_ALERT_OWN,
                'share'     => SMS_SHARE_OFF
              ));
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($data['params'][2] . "'s Nr [i]{$nr}[/i] gespeichert.");
              else 
                $bot->add_privmsg($data['params'][2] . "'s Nr [i]{$nr}[/i] ist gespeichert.", $data['user']);        
              return true;
            } else {
              $uid = $redis->HGET('handy', mb_strtolower($data['params'][1]));
              $nick = $redis->HGET("user:{$uid}:data", 'name');
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Nr [i]{$nr}[/i] ist belegt!");
              else 
                $bot->add_privmsg("Die Nr [i]{$nr}[/i] ist ".(($nick == $data['user']) ? 'von Dir ' : '')."belegt!", $data['user']);
              return true;
            }
          } else {
            $bot->add_privmsg('SMS Fehler: falsche Parameter!', $data['user']);
            return true;
          }
          break;
        case 'nr':
          if ($data['command'][0] == PRE) {
            if (!preg_match('/^(0(1|2|3|4|5|6|7|8|9)+[0-9]+[0-9]{7,9})$/', trim($data['params'][1]), $match)) {
              $message = 'Die Nr [i]' . mb_strtolower(trim($data['params'][1])) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $uid = $bot->get_user_id($data['user']);
            $nr = strval(trim($match[1]));
            $insert = $redis->HMSET("handy", array(
              $nr => $uid
            ));
            if ($insert) {// prüfen ob multi
              $redis->HMSET("user:{$uid}:sms", array(
                'number'    => $nr,
                'region'    => SMS_REGION,
                'allow'     => SMS_ALL,
                'alert'     => SMS_ALERT_OWN,
                'share'     => SMS_SHARE_OFF
              ));
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("{$data['user']}'s Nr [i]{$nr}[/i] gespeichert.");
              else 
                $bot->add_privmsg("Deine Nr [i]{$nr}[/i] ist gespeichert.", $data['user']);        
              return true;
            } else {
              $uid = $redis->HGET('handy', mb_strtolower($data['params'][1]));
              $nick = $redis->HGET("user:{$uid}:data", 'name');
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Nr [i]{$nr}[/i] ist belegt!");
              else 
                $bot->add_privmsg("Die Nr [i]{$nr}[/i] ist ".(($nick == $data['user']) ? 'von Dir ' : '')."belegt!", $data['user']);
              return true;
            }
          } else {
            if (!preg_match('/^(0(1|2|3|4|5|6|7|8|9)+[0-9]+[0-9]{7,9})$/', $data['params'][1], $match)) {
              $message = 'Die Nr [i]' . mb_strtolower($data['params'][1]) . '[/i] ist ungültig!';
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg($message);
              else 
                $bot->add_privmsg($message, $data['user']);
              return true;
            }
            $nr = strval($match[1]);
            $uid = $redis->HGET('handy', $nr);
            if ($uid) {
              $nick = $redis->HGET("user:{$uid}:data", 'name');
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Nr [i]{$nr}[/i] ist belegt!");
              else 
                $bot->add_privmsg("Die Nr [i]{$nr}[/i] ist ".(($nick == $data['user']) ? 'von Dir ' : '')."belegt!", $data['user']);
              return true;
            } else {
              if ($data["channel"] == ALLYIN)
                $bot->add_allymsg("Die Nr [i]{$nr}[/i] ist nicht belegt!");
              else 
                $bot->add_privmsg("Die Nr [i]{$nr}[/i] ist nicht belegt!", $data['user']);
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
            $set = array('on'   => SMS_ALERT_OWN, 
                         'off'  => SMS_ALERT_OFF, 
                         'an'   => SMS_ALERT_OWN, 
                         'aus'  => SMS_ALERT_OFF, 
                         'ein'  => SMS_ALERT_OWN, 
                         'ja'   => SMS_ALERT_OWN, 
                         'nein' => SMS_ALERT_OFF, 
                         '1'    => SMS_ALERT_OWN, 
                         '0'    => SMS_ALERT_OFF, 
                         'alle' => SMS_ALERT_ALL, 
                         'all'  => SMS_ALERT_ALL, 
                         'alli' => SMS_ALERT_ALL, 
                         'ally' => SMS_ALERT_ALL, 
                         'alliance' => SMS_ALERT_ALL
            );
            $redis->HMSET("user:{$uid}:sms", array(
              'alert'     => $set[mb_strtolower($data['params'][1])]
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s Eingabe wurde gespeichert.");
            else 
              $bot->add_privmsg("Deine Eingabe wurde gespeichert.", $data['user']);        
            return true;
          } else {
            $alarm = $redis->HGET("user:{$uid}:sms", 'alert');
            switch ($alarm) {
              case SMS_ALERT_OWN:
                $setting = 'Alarm = nur eigene';
                break;
              case SMS_ALERT_ALL:
                $setting = 'Alarm = alle';
                break;
              case SMS_ALERT_OFF:
                default:
                $setting = 'Alarm = aus';
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s SMS-Einstellung: " . $setting);
            else 
              $bot->add_privmsg("Deine SMS-Einstellung: " . $setting, $data['user']);        
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
            $set = array('on'   => SMS_SHARE_ON,
                         'off'  => SMS_SHARE_OFF, 
                         'an'   => SMS_SHARE_ON, 
                         'aus'  => SMS_SHARE_OFF, 
                         'ein'  => SMS_SHARE_ON, 
                         'ja'   => SMS_SHARE_ON, 
                         'nein' => SMS_SHARE_OFF, 
                         '1'    => SMS_SHARE_ON, 
                         '0'    => SMS_SHARE_OFF
            );
            $redis->HMSET("user:{$uid}:sms", array(
              'share'     => $set[mb_strtolower($data['params'][1])]
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s Eingabe wurde gespeichert.");
            else 
              $bot->add_privmsg("Deine Eingabe wurde gespeichert.", $data['user']);        
            return true;
          } else {
            $multi = $redis->HGET("user:{$uid}:sms", 'multi');
            switch ($multi) {
              case SMS_SHARE_ON:
                $setting = 'Multi = ein';
                break;
              case SMS_SHARE_OFF:
                default:
                $setting = 'Multi = aus';
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s SMS-Einstellung: " . $setting);
            else 
              $bot->add_privmsg("Deine SMS-Einstellung: " . $setting, $data['user']);        
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
            $set = array('all'        => SMS_ALL, 
                         'alle'       => SMS_ALL, 
                         'offiziere'  => SMS_OFF, 
                         'offz'       => SMS_OFF, 
                         'offi'       => SMS_OFF, 
                         'leitung'    => SMS_LEAD, 
                         'leader'     => SMS_LEAD, 
                         'lead'       => SMS_LEAD, 
                         'alarm'      => SMS_SYS,
                         'führung'    => SMS_LEAD
            );
            $redis->HMSET("user:{$uid}:sms", array(
              'allow' => $set[mb_strtolower($data['params'][1])]
            ));
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s Eingabe wurde gespeichert.");
            else 
              $bot->add_privmsg("Deine Eingabe wurde gespeichert.", $data['user']);        
            return true;
          } else {
            $allow = $redis->HGET("user:{$uid}:sms", 'allow');
            switch ($allow) {
              case SMS_ALL:
                $setting = 'Erlaubnis = alle';
                break;
              case SMS_OFF:
                $setting = 'Erlaubnis = Führung & Offiziere';
                break;
              case SMS_LEAD:
                $setting = 'Erlaubnis = Führung';
                break;
              case SMS_SYS:
                default:
                $setting = 'Erlaubnis = nur Alarm';
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}'s SMS-Einstellung: " . $setting);
            else 
              $bot->add_privmsg("Deine SMS-Einstellung: " . $setting, $data['user']);        
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
      $tonr = $redis->HGET("user:{$tuid}:sms", 'number');
      $spam = "sms:spamcheck:{$uid}";
      $date = date('dmy');
      $kontingent = "sms:kontingent:{$date}:{$uid}";
      $sms_count = "sms:count";
      $sms_costs = "sms:costs";
      $sms_send = "sms:send";
      $kontingent_ttl = mktime(0, 0, 0, date("m")  , date("d")+1, date("Y")) - time();
      if ($redis->TTL($spam) === -1) {
        $bot->log("SMSnoSPAM");
        if ($redis->SETNX($kontingent, SMS_KONTINGENT)) $redis->EXPIRE($kontingent, $kontingent_ttl);
        $count = $redis->GET($kontingent);
        if($count && $count >= 1) {
          if (!$tonr) {
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}, {$nick} hat die SMS-Funktion nicht aktiviert!");
            else 
              $bot->add_privmsg("{$nick} hat die SMS-Funktion nicht aktiviert!", $data['user']);
            return true;
          }
          $allow = $redis->HGET("user:{$tuid}:sms", 'allow');
          $role = $redis->HGET("user:{$uid}:data", 'role');          
          $access = $bot->get_access($data['user'], $allow);
          if ($access) {
            if (!($region = $redis->HGET("user:{$tuid}:sms", 'region'))) {
              $region = SMS_REGION;
              $redis->HSET("user:{$tuid}:sms", 'region', $region);
            }
            $message = "~".$bot->ally_name."~\n".(($data["channel"] != ALLYIN)? 'private ':'')."Nachricht von {$data['user']}:\n".implode(' ', $data['params']);
            if($region != SMS_REGION) {
              $message .= '\nkeine Antwort möglich!';
              $result = $sms->sendExpertSMS($tonr, $message, $region);
            } else $result = $sms->sendSMS($tonr, $message);
            $redis->SET($spam, 0, SMS_SPAMTTL);
            if ($result['error'] === true) $return = "Fehler bei der übermittlung: {$result['text']} ({$result['code']})";
            else {
              $all_count = $redis->INCR($sms_count);
              $all_costs = $redis->INCRBY($sms_costs, $result['cost']*100);
              $count = $redis->DECR($kontingent);
              $redis->HINCRBY("user:{$uid}:sms", 'sms', 1);
              $redis->HINCRBY("user:{$uid}:sms", 'cost', $result['cost']*100);
              $redis->HMSET("{$sms_send}:{$result['id']}", array(
                'returnto'  => $data["channel"],
                'status'    => SMS_STATUS_OPEN,
                'sender'    => $uid,
                'receiver'  => $tuid,
                'datetime'  => time(),
                'result'    => $result['code'],
                'cost'      => $result['cost']*100));
              $return = "{$result['text']} ({$count}/".SMS_KONTINGENT.")";
              $redis->EXPIRE($kontingent, $kontingent_ttl);
              $bot->log("SMS versendet: {$all_count}/".number_format($all_costs/100, 2, ',', '' ));
            }
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}, {$return}");
            else 
              $bot->add_privmsg($return, $data['user']);
            return true;
          } else {
            $_role = $bot->get_role($role);
            if ($data["channel"] == ALLYIN)
              $bot->add_allymsg("{$data['user']}, {$nick}'s Einstellungen lassen keine SMS von dir als {$_role} zu!");
            else 
              $bot->add_privmsg("{$nick}'s Einstellungen lassen keine SMS von dir als {$_role} zu!", $data['user']);
            return true;
          }
        } else if ($count <= 0) {
          $bot->add_privmsg("SMS-Kontingent verbraucht!", $data['user']);
          return true;
        }
      } else {
        $incr = $redis->INCR($spam) * SMS_SPAMTTL;
        $bot->add_privmsg("SMS-SpamCheck! ({$incr} sec.)", $data['user']);
        $redis->EXPIRE($spam, $incr);
        return true;
      }  
    }
    $bot->add_privmsg('SMS Fehler: falsche Parameter!', $data['user']);

  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'sms');                   
?>