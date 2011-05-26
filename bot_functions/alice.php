<?php
global $bot;
define('ALICE_ID', ''); // need an published alicebot id
$bot->add_category('alice', array(), PUBLICY);
$bot->add_allymsg_hook("Alice",                	// command key
                       "LouBot_alice",          // callback function
                       false,                 	// is a command PRE needet?
                       '/^'.$bot->bot_user_name.'[,:.-\?@]?$/i',  // optional regex für key
function ($bot, $data) {
  if(!$bot->is_himself($data['user']) && preg_match('/^'.$bot->bot_user_name.'[,:.-\?@\s]?(.*)$/i', $data['message'], $match)) {
		$request = array('botid' => ALICE_ID,
                     'input' => urlencode($match[1]),
                     'custid' => urlencode($data['user'])
    );  
    $response = alice_call($request);
    if ($response) {
      $bot->log("LoU -> get response from ALICE\n\r");
      $xml = simplexml_load_string($response);
      $result = $xml->xpath('//that');
      $bot->add_allymsg($xml->that);
    } else $bot->add_allymsg(magic_8ball());
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
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); // Timeout if it takes too long
      curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
		// initial with german language
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
    return $text[$rand_key];
  }
}

/*
$request = array('botid' => ALICE_ID,
                 'input' => urlencode('Hi'),
                 'custid' => urlencode('Tester')
);
$response = do_call($request);
print_r($response);
$xml = simplexml_load_string($response);
print_r($xml);
$result = $xml->xpath('//that'); 
print_r($result);
*/
?>