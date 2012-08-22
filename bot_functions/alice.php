<?php
global $bot;
$bot->add_category('alice', array('humanice' => true), PUBLICY);
$bot->add_allymsg_hook("Alice",                	// command key
                       "LouBot_alice",          // callback function
                       false,                 	// is a command PRE needet?
                       "/^([@]?{$bot->bot_user_name}[,.?\s]+(.*)|(.*)[,.\s]+{$bot->bot_user_name}[ ]?[.!?]?)$/i",  // optional regex für key
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
      shuffle($anrede);
      $rand_key_anrede = array_rand($anrede, 1);
    $response = alice_call($request);
    if ($response) {
      $bot->log("LoU -> get response from ALICE\n\r");
      $xml = simplexml_load_string($response);
      $result = $xml->xpath('//that');
        $reply = $xml->that;
        $reply = str_replace('<botmaster></botmaster>', BOT_OWNER, $reply);
        $reply = str_replace('<setname></setname>', $bot->get_random_nick($data['user']), $reply);
        $reply = str_replace('Blox', 'mir', $reply);
        $reply = str_replace('<getname></getname>', 'weiss aber nicht was das ist', $reply);
        //<set_thema>... <set_thema>
        $bot->add_allymsg($anrede[$rand_key_anrede] . lcfirst($reply));
      } else return $bot->add_allymsg($anrede[$rand_key_anrede] . lcfirst(magic_8ball())); // fallback
    } else {
      $incr = $redis->incr($key) * ALICETTL;
      $redis->EXPIRE($key, $incr);
      return false;
    }
  }
}, 'alice');

if(!function_exists('alice_call')) {
  function alice_call($request) {
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

if(!function_exists('magic_8ball')) {
  function magic_8ball() {
		// de
    $text_de[] = 'Da antworte ich lieber nicht, versuche es erneut :|';
    $text_de[] = 'Darauf reagier ich gar nicht...';
    $text_de[] = 'Besser nix sagen *hmpf*';
    $text_de[] = 'Konzentriere dich auf das was du eigentlich Fragen wolltest!';
    $text_de[] = 'Wie ich es sehe, ja :)';
    $text_de[] = 'Es ist sicher B-)';
    $text_de[] = 'Ja - auf jeden Fall.';
    $text_de[] = 'Höchstwahrscheinlich';
    $text_de[] = 'Empfehlenswert ;)';
    $text_de[] = 'Die Zeichen deuten auf - [b]ja[/b]';
    $text_de[] = 'Ohne Zweifel :-7';
    $text_de[] = 'Ohne Mich :-7';
    $text_de[] = 'Da kannst du dich drauf verlassen :)';
    $text_de[] = 'Es ist entschieden, so oder so *gg*';
    $text_de[] = 'Da verlässt sich keiner drauf =P';
    $text_de[] = 'Meine Antwort ist ... [i]NULL[/i]';
    $text_de[] = 'Meine Quellen sagen nein!';
    $text_de[] = 'nicht so empfehlenswert :/';
    $text_de[] = 'Sehr zweifelhaft =O';
    $text_de[] = 'glaub ich nicht ^^';
    $text_de[] = 'Wo ist denn das Problem?';
    $text_de[] = 'Wie jetzt?';
    $text_de[] = 'Dir antworte ich nicht!';
    $text_de[] = 'Sorry, jetzt muss ich gerade was anderes tun...';
    // en
    $text_en[] = 'As I see it, yes.';
    $text_en[] = 'Ask again later.';
    $text_en[] = 'Better not tell you now.';
    $text_en[] = 'Cannot predict now.';
    $text_en[] = 'Concentrate and ask again.';
    $text_en[] = 'Don\'t count on it.';
    $text_en[] = 'It is certain.';
    $text_en[] = 'It is decidedly so.';
    $text_en[] = 'Most likely.';
    $text_en[] = 'My reply is no.';
    $text_en[] = 'My sources say no.';
    $text_en[] = 'Outlook good.';
    $text_en[] = 'Outlook not so good.';
    $text_en[] = 'Reply hazy, try again.';
    $text_en[] = 'Signs point to yes.';
    $text_en[] = 'return Very doubtful.';
    $text_en[] = 'Without a doubt.';
    $text_en[] = 'Yes.';
    $text_en[] = 'Yes - definitely.';
    $text_en[] = 'You may rely on it.';
    $text = (!empty($text_{BOT_LANG})) ? $text_{BOT_LANG} : $text_en;
    shuffle($text);
    $rand_key = array_rand($text, 1);
    return $text[$rand_key];
  }
}
?>