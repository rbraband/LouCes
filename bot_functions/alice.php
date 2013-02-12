<?php
global $bot;
$bot->add_category('alice', array('humanice' => true), PUBLICY);
// crons

// callbacks
$bot->add_allymsg_hook("Alice",                 // command key
                       "LouBot_alice",          // callback function
                       false,                   // is a command PRE needet?
                       "/^([@]?{$bot->bot_user_name}[,.?\s]+(.*)|(.*)[,.\s]+{$bot->bot_user_name}[ ]?[.!?:\/()DP]{0,})$/i",  // optional regex for key
function ($bot, $data) {
  global $redis;
  if(!$bot->is_himself($data['user'])) {
    if (!$redis->status()) return $bot->add_allymsg(magic_8ball()); // fallback
    $key = "alice:spamcheck:LouBot_alice:{$data['user']}";
    $bot->log(REDIS_NAMESPACE."{$key} TTL: {$redis->ttl($key)}");
    if ($redis->ttl($key) === -1) {
      $bot->log("NoSPAM");
      $redis->set($key, 0, ALICETTL);
      $request = array('botid' => ALICEID,
                       'input' => urlencode(str_replace($bot->bot_user_name , '' , $data['message'])),
                       'custid' => urlencode($data['user'])
      );
      $anrede[] = ucfirst(strtolower($bot->get_random_nick($data['user']))) . ', ';
      $anrede[] = '@' . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' - ';
      $anrede[] = '... ';
      $anrede[] = '';
      shuffle($anrede);
      $rand_key_anrede = array_rand($anrede, 1);
    $response = alice_call($request);
    if ($response) {
        $bot->log("LoU -> get response from ALICE");
        $xml = @simplexml_load_string($response);
        if ($xml) {
          $thats = array();
          foreach ($xml->that as $that) {
             echo $thats[] = $that;
          }
          shuffle($thats);
          $rand_key = array_rand($thats, 1);
          $reply = $thats[$rand_key];
        } else {
          $bot->log('XML Error: cannot xpath Data!');
          $message = magic_8ball();
        }
        if ($reply) {
        $reply = str_replace('<botmaster></botmaster>', BOT_OWNER, $reply);
        $reply = str_replace('<setname></setname>', $bot->get_random_nick($data['user']), $reply);
          $reply = str_replace('Blox', 'mir', $reply);
        $reply = str_replace('<getname></getname>', 'weiss aber nicht was das ist', $reply);
        //<set_thema>... <set_thema>
          $message = $reply;
        } else if ($xml->message) {
          $bot->log('ALICE Error: ' . $xml->message);
          $message = magic_8ball();
        }
      } else {
        $bot->log('ALICE Error: cannot receive Data!');
        $message = magic_8ball();
      }
      return $bot->add_allymsg(($anrede[$rand_key_anrede] != '') ? $anrede[$rand_key_anrede] . lcfirst($message) : $message);
    } else {
      $incr = $redis->incr($key) * ALICETTL;
      $redis->EXPIRE($key, $incr);
      return false;
    }
  }
}, 'alice');

if(!function_exists('alice_call')) {
  function alice_call($request) {
    global $bot;
      $header[] = "Content-type: text/xml";
      $_request = "";
      foreach($request as $_key=>$_value) { $_request .= $_key.'='.$_value.'&'; }
      rtrim($_request,'&');
      $url = "http://www.pandorabots.com/pandora/talk-xml?$_request";
      
      $header[] = "Content-length: ".strlen($_request);
      $ch = curl_init();   
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100); // Timeout if it takes too long
      curl_setopt($ch, CURLOPT_TIMEOUT, 100);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    $header = curl_getinfo($ch);
      $data = curl_exec($ch);       
    if (curl_errno($ch) || empty($data)) {
      $bot->debug('Curl Error: cannot receive Data!');
          return false;
    } else if(intval($header['http_code']) >= 400) {
      $bot->debug('Http Error: ' . $header['http_code']);
      return false;
    } 
          curl_close($ch);
          return $data;
      }
}

if(!function_exists('magic_8ball')) {
  function magic_8ball() {
    // de
    $text_i18n['de'][] = 'Da antworte ich lieber nicht, versuche es erneut :|';
    $text_i18n['de'][] = 'Darauf reagier ich gar nicht...';
    $text_i18n['de'][] = 'Besser nix sagen *hmpf*';
    $text_i18n['de'][] = 'Konzentriere dich auf das was du eigentlich Fragen wolltest!';
    $text_i18n['de'][] = 'Wie ich es sehe, ja :)';
    $text_i18n['de'][] = 'Es ist sicher B-)';
    $text_i18n['de'][] = 'Ja - auf jeden Fall.';
    $text_i18n['de'][] = 'Höchstwahrscheinlich';
    $text_i18n['de'][] = 'Empfehlenswert ;)';
    $text_i18n['de'][] = 'Die Zeichen deuten auf - [b]ja[/b]';
    $text_i18n['de'][] = 'Ohne Zweifel :-7';
    $text_i18n['de'][] = 'Ohne Mich :-7';
    $text_i18n['de'][] = 'Da kannst du dich drauf verlassen :)';
    $text_i18n['de'][] = 'Es ist entschieden, so oder so *gg*';
    $text_i18n['de'][] = 'Da verlässt sich keiner drauf =P';
    $text_i18n['de'][] = 'Meine Antwort ist ... [i]NULL[/i]';
    $text_i18n['de'][] = 'Meine Quellen sagen nein!';
    $text_i18n['de'][] = 'nicht so empfehlenswert :/';
    $text_i18n['de'][] = 'Sehr zweifelhaft =O';
    $text_i18n['de'][] = 'glaub ich nicht ^^';
    $text_i18n['de'][] = 'Wo ist denn das Problem?';
    $text_i18n['de'][] = 'Wie jetzt?';
    $text_i18n['de'][] = 'Dir antworte ich nicht!';
    $text_i18n['de'][] = 'Sorry, jetzt muss ich gerade was anderes tun...';
    // en
    $text_i18n['en'][] = 'As I see it, yes.';
    $text_i18n['en'][] = 'Ask again later.';
    $text_i18n['en'][] = 'Better not tell you now.';
    $text_i18n['en'][] = 'Cannot predict now.';
    $text_i18n['en'][] = 'Concentrate and ask again.';
    $text_i18n['en'][] = 'Don\'t count on it.';
    $text_i18n['en'][] = 'It is certain.';
    $text_i18n['en'][] = 'It is decidedly so.';
    $text_i18n['en'][] = 'Most likely.';
    $text_i18n['en'][] = 'My reply is no.';
    $text_i18n['en'][] = 'My sources say no.';
    $text_i18n['en'][] = 'Outlook good.';
    $text_i18n['en'][] = 'Outlook not so good.';
    $text_i18n['en'][] = 'Reply hazy, try again.';
    $text_i18n['en'][] = 'Signs point to yes.';
    $text_i18n['en'][] = 'return Very doubtful.';
    $text_i18n['en'][] = 'Without a doubt.';
    $text_i18n['en'][] = 'Yes.';
    $text_i18n['en'][] = 'Yes - definitely.';
    $text_i18n['en'][] = 'You may rely on it.';
    
    $text = (!empty($text_i18n[BOT_LANG])) ? $text_i18n[BOT_LANG] : $text_i18n['en'];
    shuffle($text);
    $rand_key = array_rand($text, 1);
    return $text[$rand_key];
  }
}
?>