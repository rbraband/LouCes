<?php
/*
PHPLoU-bot - an LordOfUltima bot writen in PHP
Copyright (C) 2011 Roland Braband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/

class LoU implements SplSubject { 
    public $server;
    public $email;
    public $passwd;
    public $note;
    public $error;

    private $stack = array();
    private $messages = array();
    
    private $handle;
    private $data;
    private $session;
    private $msgId;
    private $connected;
    private $logging;
    private $time;
    
    const ajaxEndpoint = "/Presentation/Service.svc/ajaxEndpoint/";
    const ajaxRetrys = 3;
    const jsonClue = "\f";
    
    protected $observers= array ();
    
    static function factory($server,
                            $email,
                            $passwd) {
        
        // New IRC Object
        $lou = new LoU;
        
        // Save data
        $lou->server = $server;
        $lou->email = $email;
        $lou->passwd = $passwd;
        $lou->msgId = 0;
        $lou->connected = false;
        $lou->error = false;
        $lou->logging = true;
        // Connect to $server
        $lou->login();
        
        // Return the object
        return $lou;
    }
    
    public function set_global_chat($c = 0) {
      $this->output("LoU set chat: ". $c);	
      $this->doPoll(array("CHAT:/continent $c"), true);
      $chat = @$this->stack[0]['D'];
      if(is_array($chat)) foreach($chat as $i => $c) {
        $this->note = $this->analyse_chat($c);
				$this->output("LoU ".$this->note['channel']." to Bot Message:'". $this->note['message']. "' from ". $this->note['user']);
        $this->notify();
        unset($chat[$i]);
      }
      return true;
    }

    private function connect() {
            $_url = 'https://www.lordofultima.com/'.BOT_LANG.'/user/login?destination=%40homepage%3F';
            $_fields = array(
                'mail' => $this->email,
                'password' => $this->passwd,
                'remember_me' => 'on'
            );
            $_map_fields = array_map(create_function('$key, $value', 'return $key."=".$value;'), array_keys($_fields), array_values($_fields));
            
      $this->output('LoU login');
            $this->handle = curl_init();
						$_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
						curl_setopt($this->handle, CURLOPT_USERAGENT, $_useragent);
            curl_setopt($this->handle, CURLOPT_URL, $_url);
            curl_setopt($this->handle, CURLOPT_POST, 1);
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, implode("&", $_map_fields));
						curl_setopt($this->handle, CURLOPT_AUTOREFERER, true);
            curl_setopt($this->handle, CURLOPT_COOKIEFILE, PERM_DATA.'cookies.txt');
            curl_setopt($this->handle, CURLOPT_COOKIEJAR, PERM_DATA.'cookies.txt');
            curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($this->handle, CURLOPT_FOLLOWLOCATION, true);
            $data = curl_exec($this->handle);
            $header = curl_getinfo($this->handle);
						if (curl_errno($this->handle)) {
								$this->output("Curl Error: (".curl_errno($this->handle).") " . curl_error($this->handle));
								return false;
						} else if(intval($header['http_code']) >= 400) {
								$this->output('Http Error: ' . $header['http_code']);
								return false;
						}
            curl_close($this->handle);
            preg_match("/<input type=\"hidden\" name=\"sessionId\" id=\"sessionId\" value=\"([^\"].*)\" \/>/i", $data, $_match, PREG_OFFSET_CAPTURE);
            $_session = @$_match[1][0];
            return $this->doOpenSession($_session);
    }

    private function get($endpoint, $data, $noerror = false) {
      $this->stack = array();
      $this->error = false;
      $data = $this->getData($this->server.self::ajaxEndpoint."$endpoint", $data, $noerror);
      if (!$this->error)
        $this->stack = json_decode($data, true);
      return($this->stack);
    }
    
    private function getMsgId() {
      $this->msgId++;
      return $this->msgId;
    }
    
    public function status() {
      if(!$this->connected)
        $this->output("not logged in yet.");
      else
        $this->output("already logged in.");
      return true;
    }
    
    public function isConnected($force = false) {
      if(!$this->connected) {
        if ($force) {
          $this->output("LoU relogin");
          return $this->login();
        }
        else return false;
      }
      return true;
    }
	
    private function doOpenSession($session) {
      $this->output("LoU open session: {$session}");
      $this->get("OpenSession", array("session" => $session, "reset" => true));
      if (!$this->error) {
        $this->session = @$this->stack[i];
        return true;
      }
      return false;
    }
    
    public function login() {
      if($this->connected){
        $this->output("already logged in.");
        return true;
      }
      while ($this->connect() === false) {
				$this->output("Login error... retry!");
				usleep(mt_rand(500, 15000) * 10000);
			}
      $this->time = $this->get_time();
      $this->connected = true;
      $this->output("Login done.");
      return true;
    }
    
    private function getData($url, $data, $accept_empty_data = false) {
      // Init curl + setup variables
      $retry = self::ajaxRetrys;
      $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
      do {
        $retry--;
        $this->handle = curl_init();
        curl_setopt($this->handle, CURLOPT_USERAGENT, $_useragent);
        curl_setopt($this->handle, CURLOPT_URL, $url);
        curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, 2); // Timeout if it takes too long
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1); // Don't echo output
        curl_setopt($this->handle, CURLOPT_HTTPHEADER, array ("Accept-Encoding: gzip,deflate", "Content-Type: application/json; charset=utf-8", "Cache-Control: no-cache", "Pragma: no-cache", "X-Qooxdoo-Response-Type: application/json")); // Make it an ajax request
        curl_setopt($this->handle, CURLOPT_POST, true); // Make it post, not get.
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, json_encode($data)); // Encode JS stuff.
        curl_setopt($this->handle, CURLOPT_COOKIEFILE, PERM_DATA.'cookies.txt');
        curl_setopt($this->handle, CURLOPT_COOKIEJAR, PERM_DATA.'cookies.txt');
        $this->data = curl_exec($this->handle); // Execute!
        // Check it is not empty (and we not accept empty data!)
        if (empty($this->data) && !$accept_empty_data && curl_getinfo($this->handle, CURLINFO_HTTP_CODE) === 200) {
            if(!$retry) {
              $this->error = true;
							$this->output("Curl Error: cannot recieve Data!");
            }
        } else $retry = 0;
        if (curl_errno($this->handle)) {
          if(!$retry) {
						$this->error = true;
						$this->connected = false;
						$this->output("Curl Error: (".curl_errno($this->handle).") " . curl_error($this->handle));
					}
        }
        else curl_close($this->handle);
      } while($retry && usleep(5 * 1000));
      return $this->data;
    }
	
    public function doPoll($requests, $noerror = false) {
      if (empty($requests)) return false;
      $d = array(
          "session"   => $this->session,
          "requestid" => $this->getMsgId(),
          "requests"  =>  implode(self::jsonClue, $requests).self::jsonClue
      );
      $this->get("Poll", $d, $noerror);
    }
    
    public function doInitStats() {
      $d = array(
          "session"   => $this->session
      );
      $this->get("PlayerGetStatisticInitData", $d);
    }
    
    public function doInfoPlayer($id = -1) {
      if ($id == -1) {
        $d = array(
            "session"   => $this->session
        );
        $this->get("GetPlayerInfo", $d);
      } else {
        $d = array(
            "session"   => $this->session,
            "id"        => $id
        );
        $this->get("GetPublicPlayerInfo", $d);
      }
    }
    
    public function doGetRangePlayer($start = 0, $end = -1, $continent = -1, $type = 0) {
      $d = array(
          "session"   => $this->session,
          "start" => $start,
          "end" => $end,
          "continent" => $continent,
          "sort" => 0,
          "ascending" => true,
          "type" => $type
      );
      $this->get("PlayerGetRange", $d);
    }
    
    public function doCountAndIndexPlayer($continent = -1, $type = 0) {
      $d = array(
          "session"   => $this->session,
          "continent" => $continent,
          "sort" => 0,
          "ascending" => true,
          "type" => $type
      );
      $this->get("PlayerGetCountAndIndex", $d);
    }

    public function get_chat() {
      $message = $this->get_message();
      if (!$message) ;//$this->output("LoU check ...");	
      else $this->output("LoU send ". $message);	
      $this->doPoll(array("CHAT:{$message}"), true);
      $chat = ($this->stack[0]['C'] == 'CHAT') ? @$this->stack[0]['D'] : null;
      if(is_array($chat)) foreach($chat as $i => $c) {
        $this->note = $this->analyse_chat($c);
				$this->output("LoU ".$this->note['channel']." to Bot Message:'". $this->note['message']. "' from ". $this->note['user']);
        $this->notify();
        unset($chat[$i]);
      } else if (!$this->connected && $message != '') unshift_message($message);
      return true;
    }
    
    public function get_time() {
      $this->time = time()*1000;
      $this->output("LoU try time: {$this->time}");
      $this->doPoll(array("TIME:{$this->time}"));
      $time = ($this->stack[0]['C'] == 'TIME') ? ($this->time + @$this->stack[0]['D']['Diff']) : time()*1000;
      $this->output("LoU get time: {$time}");
      return $time;
    }
    
    public function get_continents() {
      $this->doInitStats();
      $continents = (is_array($this->stack['a'])) ? @$this->stack['a'] : null;
      if(is_array($continents)) {
				$this->note = $this->analyse_continents($continents);
				$this->output("LoU get continents: ". implode(", ", $continents));
				$this->notify();
			}
      return true;
    }
    
    public function get_self() {
      $this->doInfoPlayer();
      $self = ($this->stack) ? @$this->stack : null;
      if(is_array($self)) {
        $this->note = $this->analyse_self($self);
				$this->output("LoU get info for '{$self['Name']}'");
        $this->notify();
      }
      return true;
    }
    
    public function get_alliance() {
      $this->doPoll(array("ALLIANCE:"), true);
      $alliance = ($this->stack[0]['C'] == 'ALLIANCE') ? @$this->stack[0]['D'] : null;
      if(is_array($alliance)) {
        $this->note = $this->analyse_alliance($alliance);
				$this->output("LoU get alliance info for ".$this->note['name']."(".$this->note['short'].")");
        $this->notify();
      }
      return true;
    }
    
    public function privmsg($message, $user) {
      $message = (trim($message != '')) ? "/whisper $user $message" : null;
      $this->output("LoU -> $message\n\r");
      $this->push_message($message);
		}
    
    public function allymsg($message) {
      $message = (trim($message != '')) ? "/alliance $message" : null;
      $this->output("LoU -> $message\n\r");
      $this->push_message($message);
		}
    
    public function globlmsg($message) {
      $message = (trim($message != '')) ? "/say $message" : null;
      $this->output("LoU -> $message\n\r");
      $this->push_message($message);
		}
    
    private function push_message($message) {
      array_push($this->messages, trim($message));
    }
    
    private function get_message() {
      return array_shift($this->messages);
    }
    
    private function unshift_message($message) {
      array_unshift($this->messages, trim($message));
    }
    
    public function output($message) {
      echo date("[d/m @ H:i:s]") . trim($message) . "\n\r";
      $this->log(date("[d/m @ H:i:s]") .$message, LOG_FILE);
    }
    
    private function log($message, $file) {
      if(!$this->logging)
        return;
      $line = trim($message) . "\n";  
      error_log($line, 3, $file);
    }
	
    public function attach(SplObserver $observer) {
        $this->observers[spl_object_hash($observer)] = $observer;
    }
    
    public function detach(SplObserver $observer) {
        unset($this->observers[spl_object_hash($observer)]);
    }
    
    public function notify() {
        foreach ($this->observers as $obj) {
            $obj->update($this);
        }
    }
		
		public function clear_user_name($name) {
			if (strpos($name, ACCOUNT) === 0 ) return substr($name, 1); // name without A
      else return $name;
		}
    
    public function prepare_roles($roles) {
      $_roles = array();
      if(is_array($roles)) foreach($roles as $role) {
        $_roles[$role['i']] = LoU::prepare_role($role['n']);
      }
      return $_roles;
    }
    
    public function prepare_role($role) {
      $_roles = array('Admin'         => 'Allianzführung',
                      'Deputy admin'  => 'Stellvertreter',
                      'Member'        => 'Mitglied',
                      'Newbie'        => 'Neuling',
                      'Officer'       => 'Offizier',
                      'Veteran'       => 'Veteran');
      return (isset($_roles[$role])) ? $_roles[$role] : 'undefined';
    }
    
    public function prepare_title($title) {
      $_titles = array("1" => array("n" => "Sir",
                                   "dn" => "Freiherr",
                                   "r" => 1),
                      "2" => array("n" => "Knight",
                                   "dn" => "Ritter",
                                   "r" => 2),
                      "3" => array("n" => "Baron",
                                   "dn" => "Baron",
                                   "r" => 3),
                      "5" => array("n" => "Earl",
                                   "dn" => "Graf",
                                   "r" => 4),
                      "6" => array("n" => "Marquess",
                                   "dn" => "Marktgraf",
                                   "r" => 5),
                      "7" => array("n" => "Prince",
                                   "dn" => "Fürst",
                                   "r" => 6),
                      "8" => array("n" => "Duke",
                                   "dn" => "Herzog",
                                   "r" => 7),
                      "9" => array("n" => "King",
                                   "dn" => "König",
                                   "r" => 8),
                      "10" => array("n" => "Emperor",
                                    "dn" => "Kaiser",
                                    "r" => 9)
                      );
      return (isset($_titles[$title]['dn'])) ? $_titles[$title]['dn'] : 'undefined';
    }
    
    public function prepare_members($members) {
      $_members = array();
      /*
      "i": 8612,
      "n": "Alse",
      "r": 1476,
      "ra": 1590,
      "p": 281,
      "c": 1,
      "o": 2,
      "no": 0,
      "l": "05/06/2011 07:54:14",
      "os": 0,
      "t": 3
      */
      if(is_array($members)) foreach($members as $member) {
        $_members[$member['i']] = array('id'        => $member['i'],
                                        'name'      => $member['n'],
                                        'role'      => $member['r'],
                                        'rank'      => $member['ra'],
                                        'points'    => $member['p'],
                                        'state'     => $member['o'],
                                        'lastlogin' => $member['l'],
                                        'title'     => LoU::prepare_title($member['t']),
                                        );
      }
      return $_members;
    }
    
    public function prepare_diplomacy($diplomacys) {
      $_diplomacy = array();
      $_d = array('1' => 'BND',
                  '2' => 'FOE',
                  '3' => 'NAP'
      );
      if(is_array($diplomacys)) foreach($diplomacys as $diplomacy) {
        $_diplomacy[] = array('name' => $diplomacy['n'],
                              'state' => $_d[$diplomacy['r']]);
      }
      return $_diplomacy;
    }
    
    public function prepare_cities($cities) {
      $_cities = array();
      return $_cities;
    }
		
		public function prepare_chat($chat) {
      $_chat = preg_replace(
				'/\[\/?(hr|b|i|u|s|spieler|player|allianz|stadt|city|report|quote|url|coords)\]/',
				'',
				$chat
			);
			return trim(
				preg_replace(
					'/\s{2,}/',
					' ',
					$_chat
				)
			);
    }
    
    static function analyse_chat($data) {
        switch($data[CHANNEL]) {
          case '@A': 
            $channel = ALLYIN;
						break;
          case 'privatein':
            $channel = PRIVATEIN;
						break;
          case 'privateout':
            $channel = PRIVATEOUT;
            break;
					case '@S':
          case 'system':
            $channel = SYSTEMIN;
            break;
          case '@C': 
          case 'global': 
          default:  
            $channel = GLOBALIN;
        }
        
        $note = array('type'    => CHAT,
                      'command' => null,
                      'params'  => null,
                      'message' => LoU::prepare_chat($data[MESSAGE]),
                      'origin' 	=> $data[MESSAGE],
                      'user'    => LoU::clear_user_name($data[SENDER]),
                      'channel' => $channel);
        // Check if data is empty
        if ($data[MESSAGE] != "") {
          
          $tmp = explode(" ", $data[MESSAGE]);
          $note["command"] = trim(array_shift($tmp));
          
          // Get params
          $note["params"] = $tmp;
        }
        return $note;
    }
    
    static function analyse_alliance($data) {

        $note = array('type'      => ALLIANCE,
                      'id'        => $data['id'],
                      'name'      => $data['n'],
                      'short'     => $data['t'],
                      'announce'  => $data['a'],
                      'desc'      => $data['d'],
                      'roles'     => LoU::prepare_roles($data['r']),
                      'member'    => LoU::prepare_members($data['m']),
                      'diplomacy' => LoU::prepare_diplomacy($data['re']));
        return $note;
    }
    
    static function analyse_self($data) {

        $note = array('type'          => BOT,
                      'id'            => $data['Id'],
                      'name'          => $data['Name'],
                      'alliance'      => $data['AllianceName'],
                      'alliance_id'   => $data['AllianceId'],
                      'cities'        => LoU::prepare_cities($data['Cities']),
                      'points'        => $data['p']);
        return $note;
    }
		
		static function analyse_continents($data) {

        $note = array('type'          => STATISTICS,
                      'id'     				=> CONTINENT,
                      'data'          => $data);
        return $note;
    }
}

?>
