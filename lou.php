<?php
/*
PHPLoU-bot - an LordOfUltima bot writen in PHP
Copyright (C) 2012 Roland Braband / rbraband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/

/**
 * Exception, die geworfen wird, wenn JSON Decode fehlschlägt
 */
class JsonDecodeException extends Exception {
  function __construct($strMessage, $code = 0){
    parent::__construct($strMessage, $code);
  }
}

class JSON { 
    
  public static function Encode($obj) {
    return json_encode($obj);
  }
  
  public static function getErrorMessage($result) {
    $messages = array(
      JSON_ERROR_NONE           => 'No error has occurred',
      JSON_ERROR_DEPTH          => 'The maximum stack depth has been exceeded',
      JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
      JSON_ERROR_CTRL_CHAR      => 'Control character error, possibly incorrectly encoded',
      JSON_ERROR_SYNTAX         => 'Syntax error',
      JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    );
    return $messages[$result];
  }
 
  public static function Decode($json, $toAssoc = false) {
         
    //Remove UTF-8 BOM if present, json_decode() does not like it.
    if(substr($json, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) $json = substr($json, 3);
    
    try {
      $result = json_decode(trim($json), $toAssoc);
      $state = json_last_error();
      if ($state !== JSON_ERROR_NONE) {
        $error = JSON::getErrorMessage($state);
        throw new JsonDecodeException("JSON Error ({$state}): {$error}");       
      }
    } catch (JsonDecodeException $e){
      $line = trim(date("[d/m @ H:i:s]") . $e->getMessage()) . "\n";
      $line = trim("Debug:" . $json) . "\n";        
      error_log($line, 3, LOG_FILE);
      return false;
    }
    
    return $result;
  }
}

class Server {
  public $cWidth;
  public $cHeight;
  public $sChars;
  public $cDeep;
  public $cSpots;
  public $cX;
  public $cY;
  public $name;
  public $url;
  public $version;

  static function factory($url, $opts) {

    // New Server Object
    $server = new Server($url);

    // Save data
    $server->cWidth   = $opts['cw'];
    $server->cHeight  = $opts['ch'];
    $server->sChars   = $opts['al'];
    $server->cDeep    = $opts['cdpt'];
    $server->cSpots   = $opts['cspt'];
    $server->cX       = $opts['cx'];
    $server->cY       = $opts['cy'];
    $server->name     = $opts['n'];
    $server->version  = $opts['sv'];
    // Return the object
    return $server;
  }

  public function __construct($url) {
    $this->url = $url;
  }
}

class LoU implements SplSubject { 
  public $server;
  public $world;
  public $note;
  public $error;
  public $session;
  
  public $stack = array();
  private $messages = array();
  public $time = array('refTime'       => 0,
                       'stepTime'      => 0,
                       'diff'          => 0,
                       'serverOffset'  => 0);

  private $handle;
  private $mhandle;
  private $data;
  private $adata;
  private $msgId;
  private $connected;
  private $logging;
  private $debug;
  private $email;
  private $passwd;
  private $url;
  private $cached;
  private $clone;
  private $reLogins;
  
  const ajaxEndpoint = "Presentation/Service.svc/ajaxEndpoint/";
  const ajaxRetrys  = 3;
  const ajaxTimeout = 10;
  const ajaxMulti   = MAX_PARALLEL_REQUESTS;
  const autoCheck   = 20;
  const jsonClue    = "\f";
  const cacheTest   = true;
  const maxRelogin  = 3;

  protected $observers= array ();
  
  static function factory($url,
                          $email,
                          $passwd,
                          $debug
                          ) {
    global $redis;
    // New LoU Object
    $lou = new LoU;
    
    // Save data
    $lou->url       = $url;
    $lou->email     = $email;
    $lou->passwd    = $passwd;
    $lou->debug     = $debug;
    $lou->cached    = (!$redis->status()) ? false : true;
    // Connect to server
    $lou->login($debug);
    
    // Return the object
    return $lou;
  }

  public function __construct() {
    $this->session   = '';
    $this->msgId     = 0;
    $this->reLogins  = 0;
    $this->connected = false;
    $this->error     = false;
    $this->logging   = true;
    $this->clone     = false;
  }
  
  public function __clone() {
    $this->session   = '';
    $this->msgId     = 0;
    $this->cached    = false;
    $this->clone     = true;
  }

  public function set_global_chat($c = 0) {
    $this->output("LoU set chat: ". $c);  
    $this->doPoll(array("CHAT:/continent $c"), true);
    $chat = $this->get_stack(CHAT);
    if(is_array($chat)) foreach($chat as $i => $c) {
      $this->note = $this->analyse_chat($c);
      if ($this->note['channel'] == PRIVATEOUT) $this->output("LoU ".$this->note['channel']." to ".$this->note['user']." Message:'". $this->note['message']. "' from Bot");
      else $this->output("LoU ".$this->note['channel']." to Bot Message:'". $this->note['message']. "' from ". $this->note['user']);
      $this->notify();
      unset($chat[$i]);
    }
    return true;
  }

  private function connect($debug = false) {
    $_url = 'https://www.lordofultima.com/'.BOT_LANG.'/user/login';
    $_fields = array(
        'mail' => $this->email,
        'password' => $this->passwd,
        'remember_me' => 'on'
    );
    $_map_fields = array_map(create_function('$key, $value', 'return $key."=".$value;'), array_keys($_fields), array_values($_fields));
          
    $this->output('LoU login');
    $this->handle = curl_init();
    $_useragent = 'Mozilla/4.0 (compatible; MSIE 5.0; Windows NT 5.0)';
    curl_setopt($this->handle, CURLOPT_USERAGENT, $_useragent);
    curl_setopt($this->handle, CURLOPT_URL, $_url);
    curl_setopt($this->handle, CURLOPT_POST, true);
    curl_setopt($this->handle, CURLOPT_VERBOSE, $debug);
    curl_setopt($this->handle, CURLOPT_MAXREDIRS, self::ajaxTimeout);
    curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, self::ajaxTimeout); // Timeout if it takes too long
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
    return $this->doOpenGame($debug);
  }

  private function disconnect($debug = false) {
    $_logout_url = 'https://www.lordofultima.com/'.BOT_LANG.'/user/logout';
    $_referer_url = 'https://www.lordofultima.com/'.BOT_LANG.'/game';      
    $this->output('LoU logout');
    $this->handle = curl_init();
    $_useragent = 'Mozilla/4.0 (compatible; MSIE 5.0; Windows NT 5.0)';
    curl_setopt($this->handle, CURLOPT_USERAGENT, $_useragent);
    curl_setopt($this->handle, CURLOPT_URL, $_logout_url);
    curl_setopt($this->handle, CURLOPT_POST, false);
    curl_setopt($this->handle, CURLOPT_VERBOSE, $debug);
    curl_setopt($this->handle, CURLOPT_MAXREDIRS, self::ajaxTimeout);
    curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, self::ajaxTimeout); // Timeout if it takes too long
    curl_setopt($this->handle, CURLOPT_COOKIEFILE, PERM_DATA.'cookies.txt');
    curl_setopt($this->handle, CURLOPT_COOKIEJAR, PERM_DATA.'cookies.txt');
    curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->handle, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->handle, CURLOPT_REFERER, $_referer_url);
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
    return true;
  }

  private function doOpenGame($debug = false) {
    $_url = 'http://www.lordofultima.com/'.BOT_LANG.'/';

    $this->output('LoU open Game');
    $this->handle = curl_init();
    $_useragent = 'Mozilla/4.0 (compatible; MSIE 5.0; Windows NT 5.0)';
    curl_setopt($this->handle, CURLOPT_USERAGENT, $_useragent);
    curl_setopt($this->handle, CURLOPT_URL, $_url);
    curl_setopt($this->handle, CURLOPT_POST, false);
    curl_setopt($this->handle, CURLOPT_VERBOSE, $debug);
    curl_setopt($this->handle, CURLOPT_MAXREDIRS, self::ajaxTimeout);
    curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, self::ajaxTimeout); // Timeout if it takes too long
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
    return $this->doOpenSession($_session, $debug);
  }

  public function post($endpoint, $data = array(), $noerror = false, $debug = false) {
    $this->stack = null;
    $this->error = false;
    if ($debug) $this->debug("Post: {$endpoint}(".json_encode($data).')');
    $result = $this->getData($this->url.self::ajaxEndpoint."$endpoint", $data, $noerror, $debug);
    if (!$this->error) {
      $this->stack = $result;
    }
    return($this->stack);
  }
  
  public function postDebug($endpoint, $data = array(), $noerror = false) {
    return $this->post($endpoint, $data, $noerror, true);
  }
  
  public function get($endpoint, $data = array(), $noerror = false, $debug = false) {
    $this->stack = array();
    $this->error = false;
    if ($debug) $this->debug("Get: {$endpoint}(".json_encode($data).')');
    $json = $this->getData($this->url.self::ajaxEndpoint."$endpoint", $data, $noerror, $debug);
    if ($debug) $this->debug($json);
    if (!$this->error) {
      if (false === ($decode = $this->analyse_json($json)) || $this->error) return false;
      $this->stack = $decode;
    }
    return($this->stack);
  }

  public function getDebug($endpoint, $data = array(), $noerror = false) {
    return $this->get($endpoint, $data, $noerror, true);
  }

  public function getCached($endpoint, $data = array(), $noerror = false, $expire = 180, $debug = false) {
    global $redis;

    if (!$redis->status() || !$this->cached) return $this->get($endpoint, $data, $noerror, $debug);
    $this->stack = array();
    $this->error = false;
    $hash = md5(json_encode($data));
    $key = "cache:{$endpoint}:{$hash}";
    if (self::cacheTest || $redis->TTL($key) === -1) {
      if ($debug) $this->debug("GetCached: {$endpoint}(".json_encode($data).')');
      $json = $this->getData($this->url.self::ajaxEndpoint."$endpoint", $data, $noerror, $debug);
      if (!$this->error) {
        if (false === ($decode = $this->analyse_json($json)) || $this->error) return false;
        // test cache
        $serialized = $redis->GET($key);
        if (self::cacheTest && $serialized) {
          $unserialized = unserialize($serialized);
          $this->debug("CacheTest: ".REDIS_NAMESPACE.$key);
          if ($this->debug) {
            $diff = array_diff_assoc($unserialized, $decode);
            foreach($diff as $k => $v) $this->debug("CacheDiff[$k] ($v): cache={$unserialized[$k]} | origin={$decode[$k]}");
          }
        }
        $this->debug("CacheAdd: ".REDIS_NAMESPACE.$key);
        $redis->SET($key, serialize($decode));
        $redis->EXPIRE($key, $expire);
        $this->stack = $decode;
      }
    } else {
      $this->debug("CacheHit: ".REDIS_NAMESPACE.$key);
      $serialized = $redis->GET($key);
      if ($serialized && ($unserialized = unserialize($serialized)) && !empty($unserialized)) {
        $this->stack = $unserialized;
        $redis->EXPIRE($key, $expire);
      } else {
        $redis->DEL($key);
        return $this->getCached($endpoint, $data, $noerror, $expire, $debug);
      }
    }
    return($this->stack);
  }

  public function getCachedDebug($endpoint, $data = array(), $noerror = false, $expire = 180) {
    return $this->getCached($endpoint, $data, $noerror, $expire, true);
  }

  public function getMulti($endpoint, $multi = array(), $debug = false) {
    $this->stack = array();
    $this->error = false;
    $_multi = array();
    foreach($multi as $k => $data) {
      $hash = md5(json_encode($data));
      $_multi[$hash] = $data;
    }
    if ($debug) $this->debug("GetMulti: {$endpoint}(".json_encode($_multi).')');
    $results = $this->getDataMulti($this->url.self::ajaxEndpoint."$endpoint", $_multi, $debug);
    if (!$this->error) {
      if (is_array($results)) foreach ($results as $json) {
        if (false === ($decode = $this->analyse_json($json)) || $this->error) continue;
        $this->stack[] = $decode;
      } else {
        $this->output("LoU drop ({$results})!");
        $this->error = true;
        return false;
      }
    }
    return($this->stack);
  }

  public function getMultiDebug($endpoint, $multi = array()) {
    return $this->getMulti($endpoint, $multi, true);
  }

  public function getMultiCached($endpoint, $multi = array(), $expire = 180, $debug = false) {
    global $redis;

    if (!is_array($multi) || empty($multi)) return false;
    if (!$redis->status() || !$this->cached) return $this->getMulti($endpoint, $multi, $debug);
    $this->stack = array();
    $this->error = false;
    $_multi = array();
    foreach($multi as $data) {
      $hash = md5(json_encode($data));
      $key = "cache:{$endpoint}:{$hash}";
      if (self::cacheTest || $redis->TTL($key) === -1) {
        $_multi[$hash] = $data;
      } else {  
        $this->debug("CacheHitMulti: ".REDIS_NAMESPACE.$key);
        $serialized = $redis->GET($key);
        if ($serialized && ($unserialized = unserialize($serialized)) && !empty($unserialized)) {
          $this->stack[] = $unserialized;
          $redis->EXPIRE($key, $expire);
        } else $_multi[$hash] = $data;
      }
    }
    if (!empty($_multi)) {
      if ($debug) $this->debug("GetMultiCached: {$endpoint}(".json_encode($_multi).')');
      $results = $this->getDataMulti($this->url.self::ajaxEndpoint."$endpoint", $_multi, $debug);
      if (!$this->error) {
        if (is_array($results)) foreach ($results as $hash => $json) {
          if (false === ($decode = $this->analyse_json($json)) || $this->error) continue;
          $key = "cache:{$endpoint}:{$hash}";
          // test cache
          $serialized = $redis->GET($key);
          if (self::cacheTest && $serialized) {
            $unserialized = unserialize($serialized);
            $this->debug("CacheTest: ".REDIS_NAMESPACE.$key);
            if ($this->debug) {
              $diff = array_diff_assoc($unserialized, $decode);
              foreach($diff as $k => $v) $this->debug("CacheDiff[$k] ($v): cache={$unserialized[$k]} | origin={$decode[$k]}");
            }
          }
          $this->debug("CacheAddMulti: ".REDIS_NAMESPACE.$key);
          $redis->SET($key, serialize($decode));
          $redis->EXPIRE($key, $expire);
          $this->stack[] = $decode;
        } else {
          $this->output("LoU drop ({$results})!");
          $this->error = true;
          return false;
        }
      }
    }
    return($this->stack);
  }

  public function getMultiCachedDebug($endpoint, $multi = array(), $expire = 180) {
    return $this->getMultiCached($endpoint, $multi, $expire, true);
  }

  public function getMsgId() {
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
        if ($this->reLogins >= self::maxRelogin) {
          $this->reLogins = 0;
          $this->output("LoU logout&login");
          $this->logout();
          return $this->login();
        } else {
          $this->reLogins ++;
          $this->output("LoU relogin");
          return $this->login();
        }
      }
      else return false;
    }
    return true;
  }

  private function doOpenSession($session, $debug = false) {
    if (empty($session)) {
      $this->output("LoU can't open session!");
      return false;
    }
    $this->output("LoU open session: {$session}");
    $d = array(
        "session"   => $session,
        "reset"     => true
    );
    $this->get("OpenSession", $d, $noerror = true, $debug);
    if (!$this->error && !empty($this->stack['i'])) {
      $this->session = $this->stack['i'];
      return true;
    }
    return false;
  }

  private function getServerInfo() {
    $this->output("LoU get server info");
    $d = array(
        "session"   => $this->session
    );
    $this->get("GetServerInfo", $d);
    $server = (!$this->error && $this->stack) ? @$this->stack : null;
    if (is_array($server)) {
      $this->note = $this->analyse_server($server);
      $this->output("LoU stat server: ". $server['n']);
      $this->notify();
      return Server::factory($this->url, $server);
    }
    return false;
  }

  public function login($debug = false) {
    if($this->connected){
      $this->output("already logged in.");
      return true;
    }
    while ($this->connect($debug) === false) {
      $this->output("Login error... retry!");
      usleep(mt_rand(500, 15000) * 10000);
    }
    $this->server = $this->getServerInfo();
    $this->world = $this->server->name;
    $this->time = $this->get_time();
    if (!$this->error) {
      $this->connected = true;
      $this->output("Login done.");
    } else {
      $this->output("Login failed.");
    }
    return true;
  }
  
  public function logout($debug = false) {
    $this->connected = false;
    $this->disconnect($debug);
    $this->output("Logout done.");
    return true;
  }

  private function getData($url, $data = null, $accept_empty_data = false, $debug = false) {
    // Init curl + setup variables
    $retry = self::ajaxRetrys;
    $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
    do {
      $this->handle = curl_init();
      curl_setopt($this->handle, CURLOPT_USERAGENT, $_useragent);
      curl_setopt($this->handle, CURLOPT_URL, $url);
      curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, self::ajaxTimeout); // Timeout if it takes too long
      curl_setopt($this->handle, CURLOPT_VERBOSE, $debug);
      curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true); // Don't echo output
      curl_setopt($this->handle, CURLOPT_HTTPHEADER, array ("Accept-Encoding: gzip,deflate", "Content-Type: application/json; charset=utf-8", "Cache-Control: no-cache", "Pragma: no-cache", "X-Qooxdoo-Response-Type: application/json")); // Make it an ajax request
      curl_setopt($this->handle, CURLOPT_POST, true); // Make it post, not get.
      if (is_array($data) && !empty($data)) 
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, json_encode($data)); // Encode JS stuff.
      curl_setopt($this->handle, CURLOPT_COOKIEFILE, PERM_DATA.'cookies.txt');
      curl_setopt($this->handle, CURLOPT_COOKIEJAR, PERM_DATA.'cookies.txt');
      $this->data = curl_exec($this->handle); // Execute!
      // check http response
      // check if responce ok = 200
      if (curl_getinfo($this->handle, CURLINFO_HTTP_CODE) == 200) {
        // check it is empty and we not accept empty data!
        if (empty($this->data) && !$accept_empty_data) {
          // check retrys
          if ($retry == 0) {
            // error
            $this->error = true;
            $this->output("Curl Error: cannot recieve Data!");
            return false;
          }
        } else {
          // all ok, no retry
          $retry = 0;
        }
        // close connection
        curl_close($this->handle);
      // error here
      } else {
        // check retrys
        if($retry == 0) {
          if (curl_errno($this->handle)) {
            $this->output("Curl Error after ".self::ajaxRetrys." retrys: (".curl_errno($this->handle).") " . curl_error($this->handle));
          } else if (curl_getinfo($this->handle, CURLINFO_HTTP_CODE) >= 400) {
            $header = curl_getinfo($this->handle);
            $this->output('Http Error: ' . $header['http_code']);
          }
          $this->error = true;
          $this->connected = false;
          return false;
        } else $retry--;
      }
    } while($retry && usleep(100));
    return $this->data;
  }

  private function getDataMulti($url, $multi, $debug = false) {
    // Init multiCurl + setup variables
    // look for parralel cURLing http://php.net/manual/en/function.curl-multi-exec.php
    $max = self::ajaxMulti;
    $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
    $this->adata = array();
    $_chunks = array_chunk($multi, $max, true);
    foreach($_chunks as $_k => $_chunk) {
      $this->mhandle = curl_multi_init();
      $this->output('Curl: start parallel with chunk-' . ($_k + 1) .'/'.count($_chunks).' of ' . count($multi) . '/' . $max . ' requests!');
      $_handles = array();
      foreach($_chunk as $key => $data) {
        $_handles[$key] = curl_init($key);
        curl_setopt($_handles[$key], CURLOPT_USERAGENT, $_useragent);
        curl_setopt($_handles[$key], CURLOPT_URL, $url);
        curl_setopt($_handles[$key], CURLOPT_CONNECTTIMEOUT, self::ajaxTimeout); // Timeout if it takes too long
        curl_setopt($_handles[$key], CURLOPT_VERBOSE, $debug);
        curl_setopt($_handles[$key], CURLOPT_RETURNTRANSFER, true); // Don't echo output
        curl_setopt($_handles[$key], CURLOPT_HTTPHEADER, array ("Accept-Encoding: gzip,deflate", "Content-Type: application/json; charset=utf-8", "Cache-Control: no-cache", "Pragma: no-cache", "X-Qooxdoo-Response-Type: application/json")); // Make it an ajax request
        curl_setopt($_handles[$key], CURLOPT_POST, true); // Make it post, not get.
        curl_setopt($_handles[$key], CURLOPT_POSTFIELDS, json_encode($data)); // Encode JS stuff.
        curl_setopt($_handles[$key], CURLOPT_COOKIEFILE, PERM_DATA.'cookies.txt');
        curl_setopt($_handles[$key], CURLOPT_COOKIEJAR, PERM_DATA.'cookies.txt');
        curl_multi_add_handle($this->mhandle, $_handles[$key]);
      }
      $active = null; 
      //execute the handles
      do {
        $status = curl_multi_exec($this->mhandle, $active);
      } while ($status === CURLM_CALL_MULTI_PERFORM || $active);
      foreach ($_handles as $key => $_handle) {
        $this->adata[$key] = curl_multi_getcontent($_handle);
        curl_multi_remove_handle($this->mhandle, $_handle);
        curl_close($_handle);          
      }
      curl_multi_close($this->mhandle);
    }
    return $this->adata;
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
    $this->getCached("PlayerGetStatisticInitData", $d);
  }

  public function doRemoteSession($noerror = false) {
    $d = array(
        "session"   => $this->session,
        "requests"  => implode(self::jsonClue, array("COMO:", "DEFO", "SUBSTITUTION:")).self::jsonClue
    );
    $this->get("Poll", $d, $noerror);
  }

  public function doInfoAllianceMulti($ids) {
    foreach($ids as $id) {
      $d[] = array(
          "session"   => $this->session,
          "id"        => $id
      );
    }
    $this->getMulti("GetPublicAllianceInfo", $d);
  }

  public function doInfoAlliance($id) {
    $d = array(
        "session"   => $this->session,
        "id"        => $id
    );
    $this->getCached("GetPublicAllianceInfo", $d);
  }

  public function doInfoPlayerMulti($ids) {
    foreach($ids as $id) {
      $d[] = array(
          "session"   => $this->session,
          "id"        => $id
      );
    }
    $this->getMultiCached("GetPublicPlayerInfo", $d);
  }
  
  public function doInfoPlayer($id = -1) {
    if ($id == -1) {
      $d = array(
          "session"   => $this->session
      );
      $this->getCached("GetPlayerInfo", $d);
    } else {
      $d = array(
          "session"   => $this->session,
          "id"        => $id
      );
      $this->getCached("GetPublicPlayerInfo", $d);
    }
  }
  
  public function doInfoPlayerByName($name) {
    $d = array(
        "session"   => $this->session,
        "name"      => $name
    );
    $this->getCached("GetPublicPlayerInfoByName", $d);
  }
  
  public function doInfoCity($id) {
    $d = array(
        "session"   => $this->session,
        "id"        => $id
    );
    $this->getCached("GetPublicCityInfo", $d);
  }
  
  public function doInfoCityMulti($ids) {
    foreach($ids as $id) {
      $d[] = array(
          "session"   => $this->session,
          "id"        => $id
      );
    }
    $this->getMultiCached("GetPublicCityInfo", $d);
  }
  
  public function doPlayerCount($continent, $type = 0) {
    $d = array(
        "session"    => $this->session,
        "continent"  => $continent,
        "type"       => $type
    );
    $this->post("PlayerGetCount", $d);
  }
  
  public function doAllianceCount($continent, $type = 0) {
    $d = array(
        "session"   => $this->session,
        "continent" => $continent,
        "type"      => $type
    );
    $this->post("AllianceGetCount", $d);
  }
  
  public function doReportCount($city, $folder = 0, $mask = 0) {
    $d = array(
        "session"   => $this->session,
        "city"      => $city,
        "folder"    => $folder,
        "mask"      => $mask
    );
    $this->post("ReportGetCount", $d);
  }
  
  public function doGetReport($id) {
    $d = array(
        "session"   => $this->session,
        "id"        => $id
    );
    $this->get("GetReport", $d);
  }
  
  public function doSetIgnore($user) {
    $d = array(
        "session"   => $this->session,
        "strPlayer" => $user
    );
    $this->get("SocialCreateIgnore", $d);
  }
  
  public function doPlayerRange($start, $end, $continent, $sort = 0, $ascending = true, $type = 0) {
    $d = array(
        "session"   => $this->session,
        "continent" => $continent,
        "start"     => $start,
        "end"       => $end,
        "sort"      => LoU::get_sort_by_type($type, $sort),
        "ascending" => $ascending,
        "type"      => $type
    );
    $this->get("PlayerGetRange", $d);
  }
  
  public function doAllianceRange($start, $end, $continent, $sort = 0, $ascending = true, $type = 0) {
    $d = array(
        "session"   => $this->session,
        "continent" => $continent,
        "start"     => $start,
        "end"       => $end,
        "sort"      => $sort,
        "ascending" => $ascending,
        "type"      => $type
    );
    $this->get("AllianceGetRange", $d);
  }
  
  public function doReportRange($start, $end, $city, $sort = 0, $ascending = true, $folder = 0, $mask = 0) {
    $d = array(
        "session"   => $this->session,
        "city"      => $city,
        "start"     => $start,
        "end"       => $end,
        "sort"      => $sort,
        "ascending" => $ascending,
        "folder"    => $folder,
        "mask"      => $mask
    );
    $this->get("ReportGetHeader", $d);
  }
      
  
  public function doGetRangePlayer($start = 0, $end = -1, $continent = -1, $type = 0) {
    $this->doPlayerRange($start, $end, $continent, 0, true, $type);
  }
  
  public function doGetRangeAlliance($start = 0, $end = -1, $continent = -1, $type = 0) {
    $this->doAllianceRange($start, $end, $continent, 0, true, $type);
  }
  
  public function doCountAndIndexPlayer($continent = -1, $type = 0) {
    $d = array(
        "session"   => $this->session,
        "continent" => $continent,
        "sort"      => 0,
        "ascending" => true,
        "type"      => $type
    );
    $this->get("PlayerGetCountAndIndex", $d);
  }
  
  public function check() {
    return $this->get_chat();
  }

  public function get_chat() {
    static $version = 0;
    $message = $this->get_message();
    #if (!$message) $this->debug("LoU check ...");  
    #else $this->debug("LoU send ". $message);  
    $this->doPoll(array("UA:", "CHAT:{$message}", "IGNOREL:"), true);
    $ignorel = $this->get_stack('IGNOREL');
    if(is_array($ignorel) && $ignorel['v'] > $version) {
      $this->note = $this->analyse_self_ignorel($ignorel);
      $this->output("LoU get ignore list: {$ignorel['v']}");
      $this->notify();
    }
    $chat = $this->get_stack(CHAT);
    if(is_array($chat)) foreach($chat as $i => $c) {
      $this->note = $this->analyse_chat($c);
      if ($this->note['channel'] == PRIVATEOUT) $this->output("LoU ".$this->note['channel']." to ".$this->note['user']." Message:'". $this->note['message']. "' from Bot");
      else $this->output("LoU ".$this->note['channel']." to Bot Message:'". $this->note['message']. "' from ". $this->note['user']);
      $this->notify();
      unset($chat[$i]);
    } else if (!$this->connected && $message != '') $this->unshift_message($message);
    return true;
  }
  
  public function get_time() {
    $_time = array();
    $time = time()*1000;
    $this->output("LoU try time: {$time}");
    $this->doPoll(array("TIME:{$time}"));
    $t = (!$this->error && is_array($this->stack['TIME'])) ? @$this->stack['TIME'] : null;
    if (is_array($t)) {
      $_time = array(
        'refTime'       => $t['Ref'],
        'stepTime'      => $t['Step'],
        'diff'          => $t['Diff'],
        'serverOffset'  => $t['o'] * 60 * 60 * 1000
      );
      $this->output("LoU get ref time: {$_time['refTime']}");
      return $_time;
    }
    return false;
  }
  
  public function get_step_time($step) {
    if ($this->time['stepTime'] == 0) return time();
    return ($this->time['refTime'] + $this->time['stepTime'] * $step) / 1000;
  }
  
  public function get_continents_stat() {
    $this->doInitStats();
    $continents = (!$this->error && is_array($this->stack['a'])) ? @$this->stack['a'] : null;
    if(is_array($continents)) {
      $this->note = $this->stat_continents($continents);
      $this->output("LoU stat continents: ". implode(", " . LoU::get_continent_abbr(), $continents));
      $this->notify();
    }
    return true;
  }
  
  public function get_continents() {
    $this->doInitStats();
    $continents = (!$this->error && is_array($this->stack['a'])) ? @$this->stack['a'] : null;
    if(is_array($continents)) {
      $this->note = $this->analyse_continents($continents);
      $this->output("LoU get continents: ". implode(", " . LoU::get_continent_abbr(), $continents));
      $this->notify();
    }
    return true;
  }
  
  public function get_continent_by_koords($x, $y) {
    return (floor( $y/$this->server->cHeight ) * 10 + floor( $x/$this->server->cHeight ) );
  }
  
  public function get_continent_by_id($city_id) {
    $x = $city_id & 0xFFFF;
    $y = $city_id >> 16;
    return (floor( $y/$this->server->cHeight ) * 10 + floor( $x/$this->server->cHeight ) );
  }
  
  public function get_continent_by_pos($pos) {
    list($x, $y) = explode(':', $pos, 2);
    return $this->get_continent_by_koords(abs($x), abs($y));
  }
  
  public static function get_pos_by_id($city_id) {
    $x = str_pad($city_id & 0xFFFF, 3, "0", STR_PAD_LEFT);
    $y = str_pad($city_id >> 16, 3, "0", STR_PAD_LEFT);
    return "{$x}:{$y}";
  }
  
  public static function get_pos_by_string($string) {
    list($x, $y) = preg_split('/:/', strtolower($string), 2);
    return str_pad($x, 3 ,'0', STR_PAD_LEFT).':'.str_pad($y, 3 ,'0', STR_PAD_LEFT);
  }
  
  public static function is_string_pos($string) {
    return preg_match('/^[0-9]{1,3}:[0-9]{1,3}$/', strtolower($string));
  }
  
  public static function is_string_time($string) {
    // check if time < 23:59:59
    return preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):?([0-5][0-9])?$/', strtolower($string));
  }
  
  public static function get_duration_by_seconds($sec) {
    $hours = 0;
    $min = intval($sec / 60);
    if ($min >= 60) {
      $hours = intval($min / 60);
      $min = $min % 60;
    }
    return str_pad($hours, 2 ,'0', STR_PAD_LEFT) . ":" . str_pad($min, 2 ,'0', STR_PAD_LEFT);
  }
  
  public static function is_string_duration($string) {
    // check if duration <= 59:59
    return preg_match('/^[0-5][0-9]:[0-5][0-9][0-9]$/', strtolower($string));
  }
  
  public static function get_time_by_string($string) {
    list($hours, $minutes, $seconds) = explode(':', $string, 3);
    return intval((intval($hours) * 3600) + (intval($minutes) * 60) + intval($seconds));
  }
  
  public static function get_koords_by_id($city_id) {
    $x = $city_id & 0xFFFF;
    $y = $city_id >> 16;
    return array($x, $y);
  }
  
  public static function get_id_by_koords($x, $y) {// todo: not working!?
    $city_id = ($x << 16) & $y;
    return $city_id;
  }
  
  public function get_self() {
    $this->doInfoPlayer();
    $self = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($self)) {
      $this->note = $this->analyse_self($self);
      if (!empty($self['Name'])) { 
        $this->output("LoU get info for '{$self['Name']}'");
        $this->notify();
      } else {
        $this->output("LoU get info error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }
  
  public function get_remote_session($session) {
    if (!$this->clone) {
      $this->output("LoU try remote session without clone!");
      return false;
    }
    $this->session = $session;
    $this->doRemoteSession();
    $remote = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($remote)) {
      $this->output("LoU get remote session");
      return $remote;
    } else {
      $this->output("LoU get remote error!");
      $this->error = true;
      return false;
    }
  }
  
  public function get_player($id) {
    $this->doInfoPlayer($id);
    $player = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($player)) {
      $this->note = $this->analyse_player($player);
      if (!empty($player['n'])) { 
        $this->output("LoU get info for '{$player['n']}'");
        $this->notify();
      } else {
        $this->output("LoU get info error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }
  
  public function get_player_multi($ids) {
    $this->doInfoPlayerMulti($ids);
    $players = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($players)) {
      foreach($players as $key => $player) {
        $this->note = $this->analyse_player($player);
        if (!empty($player['n'])) { 
          $this->output("LoU get info for '{$player['n']}'");
          $this->error = false;
          $this->notify();
          if ($key % self::autoCheck == 0) $this->check();
        } else {
          $this->output("LoU get info error!");
          $this->error = true;
          continue;
        }
      }
    }
    return $this->error;
  }
  
  public function get_player_stat($id) {
    $this->doInfoPlayer($id);
    $player = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($player)) {
      $this->note = $this->stat_player($player);
      if (!empty($player['n'])) { 
        $this->output("LoU get stat for '{$player['n']}'");
        $this->notify();
      } else {
        $this->output("LoU get stat error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }

  public function get_player_stat_multi($ids) {
    $this->doInfoPlayerMulti($ids);
    $players = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($players)) {
      foreach($players as $key => $player) {
        $this->note = $this->stat_player($player);
        if (!empty($player['n'])) { 
          $this->output("LoU get stat for '{$player['n']}'");
          $this->error = false;
          $this->notify();
          if ($key % self::autoCheck == 0) $this->check();
        } else {
          $this->output("LoU get stat error!");
          $this->error = true;
          continue;
        }
      }
    }
    return $this->error;
  }
  
  public function get_player_by_name($name) {
    $this->doInfoPlayerByName($name);
    $player = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($player)) {
      $this->note = $this->analyse_player($player);
      if (!empty($player['n'])) { 
        $this->output("LoU get info for '{$player['n']}'");
        $this->notify();
      } else {
        $this->output("LoU get info error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }
  
  public function get_player_by_name_stat($name) {
    $this->doInfoPlayerByName($name);
    $player = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($player)) {
      $this->note = $this->stat_player($player);
      if (!empty($player['n'])) { 
        $this->output("LoU get stat for '{$player['n']}'");
        $this->notify();
      } else {
        $this->output("LoU get stat error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }
  
  public function get_player_by_continent($continent = -1, $type = 0) {
    $this->doPlayerCount($continent, $type);
    $count = (!$this->error && $this->stack) ? @$this->stack : null;
    if($count != -1) {
      $this->doPlayerRange(0, $count, $continent, 0, true, $type);
      $range = ($this->stack) ? @$this->stack : null;
      $this->note = $this->analyse_range_player_continent($range, $type);
      if ($continent == '-1') $this->output("LoU get {$count} residents on world (type:{$type})");
      else $this->output("LoU get {$count} residents on {$this->get_continent_abbr()}{$continent} (type:{$type})");
      $this->notify();
    }
    return true;
  }
  
  public function get_players_stat($type = 0) {
    return $this->get_player_by_continent_stat(-1, $type);
  }
  
  public function get_player_by_continent_stat($continent = -1, $type = 0) {
    $this->doPlayerCount($continent, $type);
    $count = (!$this->error && $this->stack) ? @$this->stack : null;
    if($count != -1) {
      $this->doPlayerRange(0, $count, $continent, 0, true, $type);
      $range = ($this->stack) ? @$this->stack : null;
      $this->note = $this->stat_range_player_continent($range, $continent, $type);
      if ($continent == '-1') $this->output("LoU get stat for {$count} residents on world (type:{$type})");
      else $this->output("LoU get stat for {$count} residents on {$this->get_continent_abbr()}{$continent} (type:{$type})");
      $this->notify();
    }
    return true;
  }
  
  public function get_alliance_by_continent($continent = -1, $type = 0) {
    $this->doAllianceCount($continent, $type);
    $count = (!$this->error && $this->stack) ? @$this->stack : null;
    if($count != -1) {
      $this->doAllianceRange(0, $count, $continent, 0, true, $type);
      $range = ($this->stack) ? @$this->stack : null;
      $this->note = $this->analyse_range_alliance_continent($range, $type);
      if ($continent == '-1') $this->output("LoU get {$count} alliances for world (type:{$type})");
      else $this->output("LoU get {$count} alliances for continent {$this->get_continent_abbr()}{$continent} (type:{$type})");
      $this->notify();
    }
    return true;
  }
  
  public function get_alliance_stat($type = 0) {
    return $this->get_alliance_by_continent_stat(-1, $type);
  }
  
  public function get_alliance_by_continent_stat($continent = -1, $type = 0) {
    $this->doAllianceCount($continent, $type);
    $count = (!$this->error && $this->stack) ? @$this->stack : null;
    if($count != -1) {
      $this->doAllianceRange(0, $count, $continent, 0, true, $type);
      $range = ($this->stack) ? @$this->stack : null;
      $this->note = $this->stat_range_alliance_continent($range, $continent, $type);
      if ($continent == '-1') $this->output("LoU get stat for {$count} alliances for world (type:{$type})");
      else $this->output("LoU get stat for {$count} alliances for continent {$this->get_continent_abbr()}{$continent} (type:{$type})");
      $this->notify();
    }
    return true;
  }
  
  public function get_self_alliance() {
    $this->doPoll(array("ALLIANCE:"), true);
    $alliance = $this->get_stack('ALLIANCE');
    if(is_array($alliance) && $alliance['id'] != 0) {
      $this->note = $this->analyse_self_alliance($alliance);
      $this->output("LoU get alliance info for {$this->note['name']}({$this->note['short']})");
      $this->notify();
    }
    return true;
  }
  
  public function get_self_ignorel($unignore = '') {
    static $version = 0;
    if (empty($unignore)) {
      $unignorel = 'inval';
    } else {
      if (!is_array($unignore)) $unignore = array($unignore);
      $unignorel = 'del:' . implode(',', $unignore);
    }
    $this->doPoll(array("IGNOREL:{$unignorel}"), true);
    $ignorel = $this->get_stack('IGNOREL');
    if(is_array($ignorel) && $ignorel['v'] > $version) {
      $this->note = $this->analyse_self_ignorel($ignorel);
      $this->output("LoU get ignore list: {$ignorel['v']}");
      $this->notify();
    }
    return true;
  }
  
  public function get_alliance_atts() {
    static $version = 0;
    $this->doPoll(array("ALL_AT:"), true);
    $atts = $this->get_stack('ALL_AT');
    if(is_array($atts) && $atts['v'] > $version) {
      $version = $atts['v'];
      $this->note = $this->analyse_attacks($atts);
      $this->output("LoU get attack info's ({$version})");
      $this->notify();
    }
    return true;
  }
  
  public function get_city_multi($ids) {
    $this->doInfoCityMulti($ids);
    $citys = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($citys)) {
      foreach($citys as $key => $city) {
        $this->note = $this->analyse_city($city);
        if (!empty($this->note['pos'])) { 
          $this->output("LoU get info for '{$this->note['pos']}'");
          $this->error = false;
          $this->notify();
          if ($key % self::autoCheck == 0) $this->check();
        } else {
          $this->output("LoU get info error!");
          $this->error = true;
          continue;
        }
      }
    }
    return $this->error;
  }
  
  public function get_city_multi_range($ids) {
    $this->doInfoCityMulti($ids);
    $citys = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($citys)) {
      $this->note = $this->analyse_range_city($citys);
      $this->output("LoU get range for ".count($citys)." cities");
      $this->notify();
    }
    return true;
  }
  
  public function get_city_stat_multi_range($ids, $continent = -1, $range) {
    $this->doInfoCityMulti($ids);
    $citys = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($citys)) {
      $this->note = $this->stat_range_city_continent_ids($citys, $continent, $range);
      if ($continent == '-1') $this->output("LoU get stat for ".count($citys)." cities on world");
      else $this->output("LoU get stat for ".count($citys)." cities on {$this->get_continent_abbr()}{$continent}");
      $this->notify();
    }
    return true;
  }
  
  public function get_city_stat_multi($ids) {
    $this->doInfoCityMulti($ids); 
    $citys = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($citys)) {
      foreach($citys as $key => $city) {
        $this->note = $this->stat_city($city);
        if (!empty($this->note['data']['pos'])) { 
          $this->output("LoU get stat for '{$this->note['data']['pos']}'");
          $this->error = false;
          $this->notify();
          if ($key % self::autoCheck == 0) $this->check();
        } else {
          $this->output("LoU get stat error!");
          $this->error = true;
          continue;
        }
      }
    }
    return $this->error;
  }
  
  public function get_city($id) {
    $this->doInfoCity($id);
    $city = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($city)) {
      $this->note = $this->analyse_city($city);
      if (!empty($city['pos'])) { 
        $this->output("LoU get info for '{$city['pos']}'");
        $this->notify();
      } else {
        $this->output("LoU get info error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }
  
  public function get_city_stat($id) {
    $this->doInfoCity($id);
    $city = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($city)) {
      $this->note = $this->stat_city($city);
      if (!empty($city['pos'])) { 
        $this->output("LoU get stat for '{$city['pos']}'");
        $this->notify();
      } else {
        $this->output("LoU get stat error!");
        $this->error = true;
        return false;
      }
    }
    return true;
  }
  
  public function get_city_reports($id, $from = 0, $folder = 0, $mask = 0) {
    $this->doReportCount($id, $folder, $mask);
    $count = (!$this->error && $this->stack) ? @$this->stack : null;
    if(($count - $from) >= 1) {
      $this->doReportRange($from, $count, $id, 0, true, $folder, $mask);
      $range = ($this->stack) ? @$this->stack : null;
      $this->note = $this->analyse_range_reports($id, $range, $folder);
      $this->output("LoU get ".($count - $from)."/{$count} new reports for city {$id}");
      $this->notify();
    }
    return true;
  }
  
  public function get_report($id) {
    $this->doGetReport($id);
    $report = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($report)) {
      $this->note = $this->analyse_report($report, $id);
      $this->output("LoU get info for report '{$report['sid']}'");
      $this->notify();
    }
    return true;
  }
  
  public function set_ignore($user) {
    $this->doSetIgnore($user);
    $ignorel = (!$this->error && $this->stack) ? @$this->stack : null;
    if(is_array($ignorel)) {
      foreach(LoU::prepare_ignore_list($ignorel['d']) as $k => $v) {
        if ($v['player_name'] == $user) return $k;
      }
    }
    return false;
  }
  
  public function del_ignore($ignId) {
    return $this->get_self_ignorel($ignId);
  }
  
  public function get_report_link($id) {
    $this->doGetReport($id);
    $report = (!$this->error && $this->stack) ? @$this->stack : null;
    return LoU::prepare_report_link($report['sid']);
  }
  
  public function privmsg($message, $user) {
    $message = (trim($message != '')) ? "/whisper $user $message" : null;
    $this->debug("LoU -> $message\n\r");
    $this->push_message($message);
  }
  
  public function allymsg($message) {
    $message = (trim($message != '')) ? "/alliance $message" : null;
    $this->debug("LoU -> $message\n\r");
    $this->push_message($message);
  }
  
  public function offimsg($message) {
    $message = (trim($message != '')) ? "/officer $message" : null;
    $this->debug("LoU -> $message\n\r");
    $this->push_message($message);
  }
  
  public function globlmsg($message) {
    $message = (trim($message != '')) ? "/say $message" : null;
    $this->debug("LoU -> $message\n\r");
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
    $this->log(date("[d/m @ H:i:s]") . $message, LOG_FILE);
  }
  
  public function setDebug($debug) {
    $this->debug = (bool) $debug;
  }
  
  public function debug($message) {
    if ($this->debug) {
      echo date("[d/m @ H:i:s]") . trim($message) . "\n\r";
      $this->log(date("[d/m @ H:i:s]") . $message, LOG_FILE);
    }
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
  
  static function clear_user_name($name) {
    if (strpos($name, ACCOUNT) === 0 ) return substr($name, 1); // name without A
    else if (strpos($name, LOUACB) === 0 ) return substr($name, 1); // name without B
    else if (strpos($name, LOUACC) === 0 ) return substr($name, 1); // name without C
    else if (strpos($name, UNKOWN) === 0 ) return UNKOWN; // name with $
    else return $name;
  }
  
  static function clear_alliance_name($name) {
    if (strpos($name, UNKOWN) === 0 ) return UNKOWN; // name with $
    else return $name;
  }
  
  static function prepare_roles($roles) {
    $_roles = array();
    if(is_array($roles)) foreach($roles as $role) {
      $_roles[$role['i']] = LoU::prepare_role($role['n']);
    }
    return $_roles;
  }
  
  static function prepare_credentials($roles) {
    $_credentials = array();
    if(is_array($roles)) foreach($roles as $role) {
      $_credentials[$role['i']] = LoU::prepare_power($role);
    }
    return $_roles;
  }

  static function prepare_role($role) {
    global $_GAMEDATA;
    return $_GAMEDATA->playerRoles[$role];
  }
  
  static function prepare_power($role) {
    return array(
      'Name'                                      => $role['n'],
      'IsAdmin'                                   => $role['ia'],
      'IsOfficer'                                 => $role['io'],
      'CanInvite'                                 => $role['ci'],
      'CanKick'                                   => $role['ck'],
      'CanBroadcast'                              => $role['cb'],
      'CanCreateForum'                            => $role['ccf'],
      'CanCreatePolls'                            => $role['ccp'],
      'CanEditRights'                             => $role['cer'],
      'CanModerate'                               => $role['cm'],
      'CanRepresent'                              => $role['cr'],
      'CanPromoteLowerRoles'                      => $role['cp'],
      'CanDefineAdmin'                            => $role['ia'],
      'CanDisband'                                => $role['cd'],
      'CanViewMemberReports'                      => $role['v'],
      'CanDefinePalacePriorities'                 => $role['cdp'],
      'CanSeeAllOutgoingAttacks'                  => $role['coa'],
      'CanSeeOutgoingAttacksOneHourBeforeTheyHit' => $role['co1']
    );
  }

  static function prepare_title($title) {
    global $_GAMEDATA;
    return $_GAMEDATA->playerTitles[$title]['dn'];
  }

  static function prepare_members($members) {
    $_members = array();
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
  
  static function prepare_diplomacy($diplomacys) {
    global $_GAMEDATA;
    $_diplomacy = array();
    if(is_array($diplomacys)) foreach($diplomacys as $diplomacy) {
      $_diplomacy[] = array('name' => $diplomacy['n'],
                            'state' => $_GAMEDATA->diplomacys[$diplomacy['r']]);
    }
    return $_diplomacy;
  }
  
  static function prepare_city_type($city) {
    global $_GAMEDATA;
    return $_GAMEDATA->cityTypes[$city['w']][$city['s']];
  }
  
  public function prepare_cities($cities, $alliance_id = 0) {
    $_cities = array();
    if (is_array($cities)) foreach ($cities as $_k => $data) {
      $_cities[$_k] = array('type'          => CITY,
                            'category'      => LoU::prepare_city_type($data),
                            'water'         => $data['w'],
                            'state'         => $data['s'],
                            'id'            => $data['i'],
                            'name'          => $data['n'],
                            'alliance_id'   => $alliance_id,
                            'points'        => $data['p'],
                            'pos'           => str_pad($data['x'], 3 ,'0', STR_PAD_LEFT).':'.str_pad($data['y'], 3 ,'0', STR_PAD_LEFT),
                            'x-coord'       => str_pad($data['x'], 3 ,'0', STR_PAD_LEFT),
                            'y-coord'       => str_pad($data['y'], 3 ,'0', STR_PAD_LEFT),
                            'continent'     => $this->get_continent_by_koords($data['x'], $data['y']) );
    }
    return $_cities;
  }
  
  static function prepare_chat($chat) {
    $_chat = preg_replace(
      '/\[\/?(wb|hr|b|i|u|s|spieler|player|allianz|alliance|stadt|city|report|quote|url|coords)\]/',
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
      case '@O':
        $channel = OFFICER;
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
                  'origin'  => $data[MESSAGE],
                  'user'    => LoU::clear_user_name($data[SENDER]),
                  'channel' => $channel);
    // Check if data is empty
    if ($data[MESSAGE] != "") {
      $clear = str_replace('  ', ' ', $data[MESSAGE]);
      $tmp = explode(' ', $clear);
      $note["command"] = trim(array_shift($tmp));
      
      // Get params
      $note["params"] = $tmp;
    }
    return $note;
  }
  
  static function stat_self_alliance($data) {

    $note = array('type'          => STATISTICS,
                  'id'            => ALLIANCE,
                  'data'          => LoU::analyse_self_alliance($data));
    return $note;
  }
  
  private function analyse_server($data) {

    $note = array('type'          => SYSTEM,
                  'id'            => SERVER,
                  'width'         => $data['cw'],
                  'height'        => $data['ch'],
                  'chars'         => $data['al'],
                  'name'          => $data['n'],
                  'version'       => $data['sv'],
                  'url'           => $this->url,
                  'time'          => $this->time,
                  'data'          => $data);
    return $note;
  }
  
  static function analyse_self_alliance($data) {
    $note = array('type'      => ALLIANCE,
                  'id'        => $data['id'],
                  'name'      => $data['n'],
    //            'history'   => $data['h'],
                  'short'     => $data['t'],
                  'announce'  => $data['a'],
                  'desc'      => $data['d'],
                  'roles'     => LoU::prepare_roles($data['r']),
                  'member'    => LoU::prepare_members($data['m']),
                  'diplomacy' => LoU::prepare_diplomacy($data['re']),
                  'credentials' => LoU::prepare_credentials($data['r']));
    return $note;
  }
  
  static function analyse_self_ignorel($data) {
    $note = array('type'      => LISTS,
                  'id'        => IGNORE,
                  'data'      => LoU::prepare_ignore_list($data['a']));
    return $note;
  }
  static function prepare_ignore_list($list) {
    $_list = array();
    if(is_array($list)) foreach($list as $item) {
      $_list[$item['i']] = array('id'           => $item['i'],
                                 'player_id'    => $item['pi'],
                                 'player_name'  => $item['pn'],
                                 'ally_id'      => $item['ai'],
                                 'ally_name'    => $item['an']
                                );
    }
    return $_list;
  }
  
  private function stat_player($data) {
  
    $note = array('type'          => STATISTICS,
                  'id'            => PLAYER,
                  'data'          => $this->analyse_player($data));
    return $note;
  }
  
  private function analyse_player($data) {
  
    $note = array('type'          => PLAYER,
                  'id'            => $data['i'],
                  'name'          => $data['n'],
                  'alliance'      => $data['an'],
                  'alliance_id'   => $data['a'],
                  'cities'        => $this->prepare_cities($data['c'], $data['a']),
                  'points'        => $data['p'],
                  'rank'          => $data['r']);
    return $note;
  }
  
  private function stat_report($data, $id) {
  
    $note = array('type'          => STATISTICS,
                  'id'            => REPORT,
                  'data'          => $this->analyse_report($data, $id));
    return $note;
  }
  
  private function analyse_report($data, $id) {
                                           
    $note = array('type'          => REPORT,
                  'name'          => LoU::prepare_report_link(trim($data['sid'])),
                  'id'            => $id,
                  'data'          => $data
                  );
    return $note;
  }
  
  static function prepare_report_link($sid) {
    return preg_replace('/^([A-Z0-9]{4})([A-Z0-9]{4})([A-Z0-9]{4})([A-Z0-9]{4})$/' , '${1}-${2}-${3}-${4}', $sid);
  }
  
  private function stat_city($data) {
  
    $note = array('type'          => STATISTICS,
                  'id'            => CITY,
                  'data'          => $this->analyse_city($data));
    return $note;
  }
  
  private function analyse_city($data) {
                                           
    $note = array('type'          => CITY,
                  'category'      => LoU::prepare_city_type($data),
                  'water'         => $data['w'],
                  'state'         => $data['s'],
                  'name'          => $data['n'],
                  'alliance'      => $data['an'],
                  'alliance_id'   => $data['a'],
                  'points'        => $data['po'],
                  'pos'           => str_pad($data['x'], 3 ,'0', STR_PAD_LEFT).':'.str_pad($data['y'], 3 ,'0', STR_PAD_LEFT),
                  'x-coord'       => str_pad($data['x'], 3 ,'0', STR_PAD_LEFT),
                  'y-coord'       => str_pad($data['y'], 3 ,'0', STR_PAD_LEFT),
                  'continent'     => $this->get_continent_by_koords($data['x'], $data['y']),
                  'player'        => $data['pn'],
                  'player_id'     => $data['p']
                  );
    return $note;
  }
  
  private function prepare_range_cities($data) {

    $items = array();
    if (is_array($data)) foreach($data as $item) {        
      if (is_array($item)) $items[] = $this->analyse_city($item);
    }
    return $items;
  }
  
  private function analyse_attacks($data) {
    
    $note = array('type'          => ALLYATT,
                  'id'            => $data['v'],
                  'data'          => $this->prepare_attacks($data['a']));
    return $note;
  }

  private function prepare_attacks($data) {
    $atts = array();
    if (is_array($data)) foreach($data as $att) {        
      $atts[] = array('type'       => ATTACK,
                      'id'         => $att['i'],
                      'source'     => array(
                        'city_id'     => $att['c'],
                        'city_name'   => $att['cn'],
                        'player_id'   => $att['p'],
                        'player_name' => $att['pn'],
                        'ally_id'     => $att['a'],
                        'ally_name'   => $att['an']),
                      'target'     => array(
                        'city_id'     => $att['tc'],
                        'city_name'   => $att['tcn'],
                        'player_id'   => $att['tp'],
                        'player_name' => $att['tpn']),
                      'state'      => Lou::prepare_attack_state($att['s']),
                      'eta'        => $this->get_step_time($att['es']), // estimated time of arival
                      'ete'        => $this->get_step_time($att['es'] - time()) // estimated time enroute
                      );
    }
    return $atts;
  }
  
  static function prepare_attack_state($state) {
    switch (intval($state)) {
      case 5:
        return SIEGE;
      break;
      default:
        return INCOMMING;
    }
  }

  private function prepare_range_players($data, $type = 0) {
    /*
    type 0 = points
    type 1 = maxRess
    type 2 = maxTS
    type 3 = offence
    type 4 = defence
    */
    $items = array();
    switch ($type) {
      case 0:
        if (is_array($data)) {
          foreach($data as $key => $item) {
            $ref[$item['i']] =& $data[$key];
          }
          $this->doInfoPlayerMulti(array_keys($ref));
          $players = (!$this->error && $this->stack) ? @$this->stack : null;
          if (is_array($players)) foreach($players as $player) {
            $ref[$player['i']]['c'] = (is_array($player)) ?  $this->prepare_cities($player['c'], $player['a']) : array();
            $items[] = array('type'       => PLAYER,
                             'id'         => $ref[$player['i']]['i'],
                             'cities'     => $ref[$player['i']]['c'],
                             'name'       => Lou::clear_user_name($ref[$player['i']]['n']),
                             'alliance'   => Lou::clear_alliance_name($ref[$player['i']]['a']),
                             'alliance_id'=> ($ref[$player['i']]['j']) ? $ref[$player['i']]['j'] : 0,
                             'points'     => $ref[$player['i']]['p'],
                             'rank'       => $ref[$player['i']]['r']);
          }
        }  
        break;
      case 3:
        if (is_array($data)) {
          foreach($data as $key => $item) {
            $items[] = array('type'       => PLAYER,
                             'id'         => $item['i'],
                             'name'       => Lou::clear_user_name($item['n']),
                             'alliance'   => Lou::clear_alliance_name($item['a']),
                             'alliance_id'=> ($item['j']) ? $item['j'] : 0,
                             'points'     => $item['p'],
                             'rank'       => $item['r']);
          }
        }  
        break;
      case 4:
        if (is_array($data)) {
          foreach($data as $key => $item) {
            $items[] = array('type'       => PLAYER,
                             'id'         => $item['i'],
                             'name'       => Lou::clear_user_name($item['n']),
                             'alliance'   => Lou::clear_alliance_name($item['a']),
                             'alliance_id'=> ($item['j']) ? $item['j'] : 0,
                             'points'     => $item['p'],
                             'rank'       => $item['r']);
          }
        }  
        break;
    }
    
    return $items;
  }
  
  private function prepare_range_alliances($data, $type = 0) {
    $items = array();
    switch ($type) {
      case 0:
        if (is_array($data)) foreach($data as $item) {        
          $items[] = array('type'       => ALLIANCE,
                           'id'         => $item['i'],
                           'cities'     => $item['c'],
                           'name'       => $item['n'],
                           'points'     => $item['p'],
                           'rank'       => $item['r'],
                           'members'    => $item['m'],
                           'average'    => $item['a']);
        }
        break;
    }
    return $items;
  }
  
  private function stat_range_city_continent_ids($data, $continent, $range) {
    
    $note = array('type'          => STATISTICS,
                  'id'            => CITY.RANGE,
                  'range'         => $range,
                  'continent'     => $continent,
                  'data'          => $this->analyse_range_city($data));
    
    return $note;
  }
  
  private function analyse_range_city($data) {
    
    $note = $this->prepare_range_cities($data);
    
    return $note;
  }
  
  private function stat_range_player_continent($data, $continent, $type = 0) {
    
    $note = array('type'          => STATISTICS,
                  'id'            => PLAYER.RANGE,
                  'range'         => $type,
                  'continent'     => $continent,
                  'data'          => $this->analyse_range_player_continent($data, $type));
    
    return $note;
  }
  
  private function analyse_range_player_continent($data, $type = 0) {
    
    $note = $this->prepare_range_players($data, $type);
    
    return $note;
  }
  
  private function stat_range_alliance_continent($data, $continent, $type = 0) {
    
    $note = array('type'          => STATISTICS,
                  'id'            => ALLIANCE.RANGE,
                  'range'         => $type,
                  'continent'     => $continent,
                  'data'          => $this->analyse_range_alliance_continent($data, $type));
    
    return $note;
  }
  
  private function analyse_range_alliance_continent($data, $type = 0) {
    
    $note = $this->prepare_range_alliances($data, $type);
    
    return $note;
  }
  
  private function analyse_range_reports($id, $data, $folder = 0) {
    
    $note = array('type'          => REPORTHEADER,
                  'id'            => $id,
                  'data'          => $this->prepare_range_reports($data, $folder));
    
    return $note;
  }
  
  private function prepare_range_reports($data, $folder = 0) {

    $items = array();
    switch ($folder) {
      case 0:
        if (is_array($data)) foreach($data as $item) {        
          $items[] = array('type'           => REPORTHEADER,
                           'time'           => round($item['d'] / 1000, 0),
                           'id'             => $item['i'],
                           'name'           => $item['l'],
                           'owner'          => $item['o'],
                           'owner_name'     => $item['on'],
                           'opponent'       => $item['p'],
                           'report_origin'  => $item['t'],
                           'report_type'    => Lou::prepare_report_type($item['t']),
                           'report_text'    => Lou::prepare_report_text($item['t'], $item['p'], $item['l']));
        }
        break;
    }
    return $items;
  }
  
  static function prepare_report_type($type) {
    global $_GAMEDATA;
    $_id = substr($type, 1, 4);#1111520
    if (preg_match('/([^:][a-z]+):/i', $_GAMEDATA->reports[$_id]['n'], $match)) $_type = (!empty($_GAMEDATA->translations['tnf:'.strtolower(trim($match[1]))])) ? $_GAMEDATA->translations['tnf:'.strtolower(trim($match[1]))] : ucfirst(trim($match[1]));
    else if(strtolower($_GAMEDATA->reports[$_id]['n']) == 'enlightment') $_type = $_GAMEDATA->translations['tnf:enlightenment'];
    else if(stripos($_GAMEDATA->reports[$_id]['n'], 'Old Report') !== false) $_type = 'Old Report';
    else if(stripos($_GAMEDATA->reports[$_id]['n'], 'failed transport') !== false) $_type = 'Transport';
    else if(preg_match('/([^\s][a-z]+)\s/i', $_GAMEDATA->reports[$_id]['n'], $match)) $_type = (!empty($_GAMEDATA->translations['tnf:'.strtolower(trim($match[1]))])) ? $_GAMEDATA->translations['tnf:'.strtolower(trim($match[1]))] : ucfirst(trim($match[1]));
    else $_type = 'Unknown Report';
    return trim($_type);
  }
  
  static function prepare_report_text($type, $arg1 = '', $arg2 = '') {
    global $_GAMEDATA;
    $_id = substr($type, 1, 4);#1111520
    $_text = preg_replace('/(%\d)/', '${1}$s', $_GAMEDATA->reports[$_id]['sl']);
    
    return trim(sprintf($_text, $arg1, $arg2)); 
  }
  
  static function stat_self($data) {

    $note = array('type'          => STATISTICS,
                  'id'            => BOT,
                  'data'          => LoU::analyse_self($data));
    return $note;
  }
  
  static function analyse_self($data) {

    $note = array('type'          => BOT,
                  'id'            => $data['Id'],
                  'name'          => $data['Name'],
                  'alliance'      => $data['AllianceName'],
                  'alliance_id'   => $data['AllianceId'],
                  'cities'        => array(),
                  'points'        => $data['p']);
    return $note;
  }
  
  static function stat_continents($data) {

    $note = array('type'          => STATISTICS,
                  'id'            => CONTINENT,
                  'data'          => LoU::analyse_continents($data));
    return $note;
  }
  
  static function analyse_continents($data) {

    $note = array('type'          => CONTINENT,
                  'continents'    => $data);
    return $note;
  }
  
  static function get_sort_by_type($type, $sort = 0) {
    // need all types
    /*
      PRanking = 10,
      PResources = 11,
      PMilitary = 12,
      POffense = 13,
      PDefense = 14,
      PUnits = 15,
      PPlunder = 16,
      PFaith = 17,
      ARanking = 20,
      AUnits = 21,
      AFaith = 22,
      ALoU = 23
    */
    switch ($type) {
      case 0:
        if (in_array($sort, array(0,1,2,3,4))) return $sort;
        else return 0;
        break;
      case 1:
        if (in_array($sort, array(5,6,7,8,9,10))) return $sort;
        else return 5;
        break;
      case 2:
        if (in_array($sort, array(11,12,13))) return $sort;
        else return 11;
        break;
      case 3:
        if (in_array($sort, array(14))) return $sort;
        else return 14;
        break;
      case 4:
        if (in_array($sort, array(15))) return $sort;
        else return 15;
        break;
      case 10:
        if (in_array($sort, array(29,1,3,0))) return $sort;
        else return 29;
        break;  
    }
  }
  
  static function get_continent_abbr() {
    global $_GAMEDATA;
    return substr($_GAMEDATA->translations['tnf:continent'], 0, 1);
  }
  
  private function analyse_json($string) {
    try {
      $decode = JSON::Decode($string, true);
      if (is_array($decode) && $decode != null) {
        foreach($decode as $k => $v) {
          if (@array_key_exists('C', $v) && $v['C'] == SYS && $v['D'] == KICKED) {
            $this->error = KICKED;
            $this->output("LoU kicked :(");
            $this->connected = false;
            return false;
          } else if (@array_key_exists('C', $v) && $v['C'] == SYS && $v['D'] == CLOSED) {
            $this->error = CLOSED;
            $this->output("LoU close :(");
            $this->connected = false;
            return false;
          }
          if (@array_key_exists('C', $v) && $v['C'] == VERSION) {
            continue;
          }
        }
        return $decode;
      }
    } catch (Exception $e){
       $this->output($e);
    }
    return false;
  }

  public function get_stack($key) {
    if (is_array($this->stack) && !$this->error) {
      foreach($this->stack as $k => $v) {
        if (@array_key_exists('C', $v) && $v['C'] == $key) {
          return $v['D'];
        }
      }
    }
    return null;
  }
}

?>