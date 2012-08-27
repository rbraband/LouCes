#!/usr/bin/php
<?php
/*
PHPLoU_bot - an LoU bot writen in PHP
Copyright (C) 2012 Roland Braband / rbraband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/
 
// include main files
#if (!defined('STD_PROP_LIST')) include_once('./libs/ArrayObject.php');
// todo: implement __autoload
include_once('config.php');
include_once('lou.php');
include_once('redis.php');
include_once('cron.php');
include_once('forum.php');
include_once('igm.php');
include_once('sms.php');
#include_once('mysql.php');
include_once('fork.php');

/**
 * Klasse, welche überprüft, ob noch eine andere Instanz des Bot läuft
 */
class LockManager {
  private $fh;
  private $fn;
  /*
   * Konstruktor
   * 
   * @param string $filename Dateiname, der als Pseudo-Lock-File benutzt werden soll
   * @throws LockManagerRunningException, wenn bereits eine Instanz läuft
   */
  public function __construct($filename) {
    $this->fn = $filename;
    $this->fh = @fopen($this->fn, 'w');
    if (!flock($this->fh, LOCK_EX + LOCK_NB)) {
      throw new LockManagerRunningException('Bot already running!');
    } else {
      declare(ticks=10000);
      register_tick_function(array(&$this, 'reLock'), true);
    }
  }
  
  public function reLock() {
    if (false === @get_resource_type($this->fh)) {
      $this->fh = @fopen($this->fn, 'w');
      if (!flock($this->fh, LOCK_EX + LOCK_NB)) {
        throw new LockManagerRunningException('Can\'t reLock!');
      }
    } else flock($this->fh, LOCK_EX + LOCK_NB);
    ftruncate($this->fh, 0);
  }
}
 
/**
 * Exception, die geworfen wird, wenn bereits eine Instanz läuft
 */
class LockManagerRunningException extends Exception {
  function __construct($strMessage, $code = 0){
    parent::__construct($strMessage, $code);
  }
}

class executeThread extends PHP_Fork {
    public $worker;
    
    public function __construct($name) {
        $this->PHP_Fork($name);
    }
    
    public function run() {
      return call_user_func_array(array($this, 'worker'), func_get_args());
    }
    
    public function __call($method, $args) {
      if ($this->{$method} instanceof Closure) {
        return call_user_func_array($this->{$method}, $args);
      } else {
        try {
          return parent::__call($method, $args);
        } catch (PHP_ForkException $e){
          $line = trim(date("[d/m @ H:i:s]") . "PHP_Fork command ('{$method}' on '{$this->getName()}') Error: " . $e->getMessage()) . "\n";  
          error_log($line, 3, LOG_FILE);
          return false;
        }
      }
    }
    
    public function __destruct() {
      $this->_cleanThreadContext();
    }
}

class Category implements SplObserver {
  private $enabled;
  private $access;
  private $rules = array();
  
  // spezial rules options
  private $timeout;
  private $fuzzyit;
  private $schedule;
  private $randomice;
  private $spamsafe;
  
  public $name;
  public $dobreak;
  
  public function __construct() {
    $this->enabled = true;
  }
  
  static function factory($name,
                          $rules,
                          $access) {
    $category = new Category;
    $category->name = $name;
    $category->rules = $rules;
    $category->access = $access;
    $category->setRules();
    return $category;
  }
  
  public function getName() {
    return $this->name;
  }
  
  public function getAccess() {
    return $this->access;
  }
  
  public function enable() {
    $this->enabled = true;
  }
  
  public function disable() {
    $this->enabled = false;
  }
  
  public function isEnabled() {
    return $this->enabled;
  }
  
  private function setRules() {
    $this->timeout = intval(@$this->rules['timeout']);
    $this->fuzzyit = (@$this->rules['fuzzyit']) ? true : false;
    $this->schedule = intval(@$this->rules['schedule']);
    $this->randomice = (@$this->rules['randomice']) ? true : false;
    $this->spamsafe = (@$this->rules['spamsafe']) ? true : false;
    $this->dobreak = (@$this->rules['dobreak']) ? true : false;
    $this->enabled = (@$this->rules['enabled']) ? $this->rules['enabled'] : true;
  }
  
  public function update(SplSubject $subject) {
    global $bot;
    //$bot->log("Category: ".$this->name);
    $return = ($this->isEnabled() && $this->checkAccess($subject));
    if ($return && $this->fuzzyit) $return = $this->fuzzyIt();
    if ($return && $this->spamsafe) $return = $this->spamCheck($subject);
    if ($return && $this->randomice) $return = $this->randomSleep();
    return $return;
  }
  
  private function randomSleep() {
    usleep(mt_rand(500, 1500) * 1000);
    return true;
  }
  
  private function fuzzyIt() {
    $fuzzy = mt_rand(1, 1000);
    if ($fuzzy % 2 == 0) { 
      return true;
    } 
    else { 
      return false;
    }
  }
  
  private function checkAccess($hook) {
    return true; // later implemented
  }
  
  private function spamCheck($hook) {
    global $redis, $bot;
    if (!$redis->status()) return true;
    $key = "{$this->name}:spamcheck:{$hook->name}:{$hook->input['user']}";
    $bot->log(REDIS_NAMESPACE."{$key} TTL: {$redis->ttl($key)}");
    if ($redis->ttl($key) === -1) {
      $bot->log("NoSPAM");
      $redis->set($key, 0, SPAMTTL);
      return true;
    } else {
      $incr = $redis->incr($key) * SPAMTTL;
      $bot->add_privmsg("SpamCheck! ($incr sec.)", $hook->input['user']);
      $redis->expire($key, $incr);
      return false;
    }
  }

}

class Hook implements SplSubject {
  private $command;
  private $is_command;
  private $regex;
  private $disabled;
  
  public $name;
  public $func;
  public $category;
  public $input;
  
  protected $observers= array ();
  
  public function __construct() {
    $this->disabled = false;
  }
  
  static function factory($command,
                          $name,
                          $is_command,
                          $regex,
                          $func,
                          $category) {
    $hook = new Hook;
    $hook->command = $command;
    $hook->name = $name;
    $hook->is_command = ($is_command == true) ? true : false;
    $hook->regex = ($regex != '') ? $regex : "/^{$command}$/";
    $hook->func = $func;
    $hook->category = $category;
    $hook->attach($hook->category);
    return $hook;
  }
  
  public function isCommand() {
    return $this->is_command;
  }
  
  public function callFunction($subject, $input) {
    $anonym = $this->func;
    $this->input = $input;
    $subject->debug('Hook->'.$this->name);
    if ($this->notify()) return $anonym($subject, $input);
  }
  
  public function getCommand() {
    return $this->command;
  }
  
  public function compCommand($compare) {
    if ($this->isCommand()) {
      if ($compare['command'][0] != PRE) return false;
      else return preg_match($this->evalRegex(), substr($compare['command'], 1));
    } else {
      return (preg_match($this->evalRegex(), $compare['command']) || preg_match($this->evalRegex(), $compare['message']));
    }
  }
  
  private function evalRegex() {
    global $bot;
    $regex = $this->regex;
    eval ("\$regex = \"$regex\";");
    return $regex;
  }
  
  public function attach(SplObserver $observer) {
    $this->observers[spl_object_hash($observer)] = $observer;
  }
  
  public function detach(SplObserver $observer) {
    unset($this->observers[spl_object_hash($observer)]);
  }
  
  public function notify() {
    $return = true;
    foreach ($this->observers as $obj) {
      $return = $obj->update($this);
    }
    return $return;
  }
  
  public function breakThis() {
    $return = false;
    foreach ($this->observers as $obj) {
      $return = $obj->dobreak;
    }
    return $return;
  }
}

class LoU_Bot implements SplObserver {
    public $ally_name;
    public $ally_id;
    public $ally_shortname;
    public $bot_user_name;
    public $bot_user_id;
    public $server = BOT_SERVER;
    public $email = BOT_EMAIL;
    public $password = BOT_PASSWORD;
    public $owner = BOT_OWNER;
    public $globalchat = false;
    public $globalbridge = false;
    
    public $lock;
    public $cron;
    public $lou;
    public $forum;
    public $igm;
    public $categories = array();
    
    private $hooks = array();
    private $events = array();
    private $logging = true;
    private $stop = false;
    private $debug = false;
    
    public function __construct() {
      global $_ARG;
      $this->debug = $_ARG->debug;
      try {
        $this->lock = new LockManager(LOCK_FILE);
      } catch (LockManagerRunningException $e) {
        die("Bot läuft bereits...\n");
      }
    }
    
    public function __destruct() {
      $this->debug("Terminated: " . posix_getpid());
    }
  
    public function run() {
      $this->add_category('default', array('humanice' => true), PUBLICY);
      $this->cron = Cron::factory();
      $this->cron->attach($this);
      $this->lou = LoU::factory($this->server,
                                $this->email,
                                $this->password,
                                $this->debug);
      if ($this->debug) $this->log("Entered debugmode!");
      $this->lou->attach($this);
      $this->lou->get_self();        
      $this->forum = Forum::factory($this->lou);
      $this->igm = Igm::factory($this->lou);
      $this->load_hooks();
      $this->globalchat = (defined('GLOBALCHAT')) ? GLOBALCHAT : false;
      while ($this->lou->isConnected(true)) {
        $slepp_until = time() + POLLTRIP;
        $event = $this->cron->check();
        $chat = $this->lou->check();
        if (time() < $slepp_until) time_sleep_until($slepp_until);
      }
    }
    
    public function add_category($category, $rules = array(), $access = PUBLICY) {
      if (!is_object(@$this->categories[md5(strtoupper($category))]))
        $this->categories[md5(strtoupper($category))] = Category::factory($category,
                                                                          $rules,
                                                                          $access);
    }
    
    public function get_category($category) {
      if (!is_object(@$this->categories[md5(strtoupper($category))]))
        $this->add_category($category);
      return $this->categories[md5(strtoupper($category))];
    }
    
    public function add_globlmsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $this->hooks[GLOBALIN][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              $is_command,
                                              $regex,
                                              $function,
                                              $this->get_category($category));
    }

    public function add_privmsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $this->hooks[PRIVATEIN][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              $is_command,
                                              $regex,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_provmsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $this->hooks[PRIVATEOUT][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              $is_command,
                                              $regex,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_allymsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $this->hooks[ALLYIN][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              $is_command,
                                              $regex,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_offimsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $this->hooks[OFFICER][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              $is_command,
                                              $regex,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_msg_hook($msg_hook, $command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $_channels = array(OFFICER => 'add_offimsg_hook', ALLYIN => 'add_allymsg_hook', PRIVATEIN => 'add_privmsg_hook', PRIVATEOUT => 'add_provmsg_hook', GLOBALIN => 'add_globlmsg_hook');
      if (is_array($msg_hook)) {foreach($msg_hook as $msg) {if (array_key_exists($msg, $_channels)) $this->{$_channels[$msg]}($command, $name, $is_command, $regex, $function, $category);}}
      else $this->{$_channels[$msg_hook]}($command, $name, $is_command, $regex, $function, $category);
    }
    
    public function add_user_hook($command, $name, $function, $category = 'user') {
      $this->hooks[USER][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_bot_hook($command, $name, $function, $category = 'bot') {
      $this->hooks[BOT][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_alliance_hook($command, $name, $function, $category = 'alliance') {
      $this->hooks[ALLIANCE][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_attack_hook($command, $name, $function, $category = 'attacks') {
      $this->hooks[ALLYATT][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_report_hook($command, $name, $function, $category = 'reports') {
      $this->hooks[REPORT][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_reportheader_hook($command, $name, $function, $category = 'reports') {
      $this->hooks[REPORTHEADER][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_system_hook($command, $name, $function, $category = 'system') {
      $this->hooks[SYSTEM][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_statistic_hook($command, $name, $function, $category = 'statistic') {
      $this->hooks[STATISTICS][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
    
    public function add_tick_event($events, $command, $name, $function, $category = 'tick') {
      if (!is_array($events)) $events = array($events);
      foreach($events as $event) {
        if (!empty($event)) $this->events[$event][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
      }
    }
    
    public function add_cron_event($events, $command, $name, $function, $category = 'cron') {
      if (!is_array($events)) $events = array($events);
      foreach($events as $event) {
        if (!empty($event)) $this->events[$event][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
      }
    }

    public function update(SplSubject $subject) {
      while($this->stop) {
        $this->log("Wait for reload!");
        usleep(25 * 1000);
      }
      $input = $subject->note;
      switch($input['type']) {
        case CHAT:
          $this->debug("Fire".ucfirst(strtolower($input['type']))."Hooks ({$input['channel']})");
          $hooks = @$this->hooks[$input['channel']];
          if (is_array($hooks)) foreach ($hooks as $hook) {
            if ($hook->compCommand($input)) {
              $hook->callFunction($this, $input);
              if ($hook->breakThis()) break;
            }
          }
          break;
        case ALLYATT:
        case REPORTHEADER:
        case STATISTICS:
        case SYSTEM:
          $this->debug("Fire".ucfirst(strtolower($input['type']))."Hooks ({$input['id']})");
          $hooks = @$this->hooks[$input['type']];
          if (is_array($hooks)) foreach ($hooks as $hook) {
            $hook->callFunction($this, $input);
            if ($hook->breakThis()) break;
          }
          break;
        case ALLIANCE:
        case REPORT:
        case USER:
          $this->debug("Fire".ucfirst(strtolower($input['type']))."Hooks ({$input['name']})");
          $hooks = @$this->hooks[$input['type']];
          if (is_array($hooks)) foreach ($hooks as $hook) {
            $hook->callFunction($this, $input);
            if ($hook->breakThis()) break;
          }
          break;
        case BOT:
          $this->log("Set Bot-Data ++++++++++++++++++++");
          $this->set_bot_user_id($input['id']);
          $this->log("Set UserId: " . $this->bot_user_id);
          $this->set_bot_user_name($input['name']);
          $this->log("Set Name: " . $this->bot_user_name);
          $this->set_ally_name($input['alliance']);
          $this->log("Set Alliance: " . ((!empty($this->ally_name)) ? $this->ally_name : 'none'));
          $this->set_ally_id($input['alliance_id']);
          $this->log("Set AllianceId: " . $this->ally_id);
          $this->debug("Fire".ucfirst(strtolower($input['type']))."Hooks ({$input['name']})");
          $hooks = @$this->hooks[$input['type']];
          if (is_array($hooks)) foreach ($hooks as $hook) {
            $hook->callFunction($this, $input);
            if ($hook->breakThis()) break;
          }
          break;
        case CRON:
        case TICK:
          $this->debug("Fire".ucfirst(strtolower($input['type']))."Events ({$input['name']})");
          $events = @$this->events[$input['name']];
          if (is_array($events)) { sort($events); foreach ($events as $event) {
            $event->callFunction($this, $input);
            if ($event->breakThis()) break;
          } }
          break;
      }
    }
    
    public function call_event($input, $name = null) {
      $this->debug("Call".ucfirst(strtolower($input['type']))."Events ({$input['name']})");
          $events = @$this->events[$input['name']];
      if (is_array($events)) { sort($events); foreach ($events as $event) {
        if (is_null($name) || strtolower($name) == strtolower($event)) $event->callFunction($this, $input);
            if ($event->breakThis()) break;
      } }
    }
    
    public function call_hook($input, $name = null) {
      if (isset($input['type'])) {
        $hooks = @$this->hooks[$input['type']];
        $this->debug("Call".ucfirst(strtolower($input['type']))."Hooks ({$input['name']}:{$input['id']})");
      } else if (isset($input['channel'])) {
        $hooks = @$this->hooks[$input['channel']];
        $this->debug("Fire".ucfirst(strtolower($input['type']))."Hooks ({$input['channel']})");
      }
      if (is_array($hooks)) foreach ($hooks as $hook) {
        if (is_null($name) || strtolower($name) == strtolower($hook)) $hook->callFunction($this, $input);
        if ($hook->breakThis()) break;
      }
    }
    
    public function kick_event() {
      ;// deprecated
          }
    
    public function set_global_bridge($state = false) {
      $this->globalbridge = $state;
    }
    
    public function reply_msg($type, $message, $user = null) {
      switch($type) {
        case PRIVATEIN:
          $this->add_privmsg($message, $user);
          break;
        case ALLYIN:
          $this->add_allymsg($message);
          break;
        case GLOBALIN:
          $this->add_globlmsg($message);
          break;
        case OFFICER:
          $this->lou->offimsg($message);
          break;
      }
    }
    
    public function add_privmsg($message, $user) {
      $this->lou->privmsg($message, $user);
    }
    
    public function add_globlmsg($message) {
      $this->lou->globlmsg($message);
    }
    
    public function add_offimsg($message) {
      $this->lou->offimsg($message);
    }
    
    public function add_allymsg($message) {
      if ($this->globalbridge) $this->lou->globlmsg($message);
      else $this->lou->allymsg($message);
    }
    
    public function set_ally_id($id) {
       $this->ally_id = $id;
    }
    
    public function set_ally_name($name) {
       $this->ally_name = $name;
    }
    
    public function set_ally_shortname($name) {
       $this->ally_shortname = $name;
    }
    
    public function set_bot_user_id($id) {
       $this->bot_user_id = $id;
    }
    
    public function set_bot_user_name($name) {
       $this->bot_user_name = $name;
    }
    
    public function get_bot_user_name($name) {
       return $this->bot_user_name;
    }
    
    public function is_himself($name) {
      return (mb_strtoupper($name) == mb_strtoupper($this->bot_user_name))? true : false;
    }
    
    public function is_ally_user($user) {
      global $redis;
      if (empty($user)||!$redis->status()) return false;

      $alliance_key = "alliance:{$this->ally_id}";
      if ($redis->sIsMember("{$alliance_key}:member", $user)) return true;
      else {
        $uid = $redis->hGet('aliase', mb_strtoupper($user));
        if ($redis->hGet("user:{$uid}:data", 'alliance') == $this->ally_id) return true;
        else return false;
      }
    }
    
    public function get_user_id($user) {
      global $redis;
      if (empty($user)||!$redis->status()) return false;
      return $redis->hGet('aliase', mb_strtoupper($user));
    }
    
    public function get_user_by_hash($hash) {
      global $redis;
      if (empty($hash)||!$redis->status()) return false;
      return $redis->hGet('hashes', $hash);
    }
    
    public function set_user_hash($user) {
      global $redis;
      if (empty($user)||!$redis->status()) return false;
      if($uid = $redis->hGet('aliase', mb_strtoupper($user))) {
        $newhash = md5(uniqid($uid, true));
        if($oldhash = $redis->hGet("user:{$uid}:data", 'hash')) $redis->hDel('hashes', $oldhash);
        $redis->hSet("user:{$uid}:data", 'hash', $newhash); 
        $redis->hSet('hashes', $newhash, $uid);
        return $newhash;
      } else return false;
    }
    
    public function set_hash($user, $extension) {
      global $redis;
      if (empty($user)||!$redis->status()) return false;
      if($uid = $redis->hGet('aliase', mb_strtoupper($user))) {
        $newhash = md5(uniqid($uid, true));
        if($oldhash = $redis->hGet("user:{$uid}:data", $extension)) $redis->hDel('hashes', $oldhash);
        $redis->hSet("user:{$uid}:data", $extension, $newhash); 
        $redis->hSet('hashes', $newhash, $uid);
        return $newhash;
      } else return false;
    }
    
    public function get_user_name_by_id($uid) {
      global $redis;
      if (empty($uid)||!$redis->status()) return false;
      return $redis->hGet("user:{$uid}:data", 'name');
    }
    
    public function get_user_random_nick_by_id($uid) {
      global $redis;
      if (empty($uid)||!$redis->status()) return false;
      return $redis->sRandMember("user:{$uid}:alias");
    }
    
    public function get_random_nick($user) {
      global $redis;
      if (empty($user)) return false;
      else if ($redis->status()) {
        $uid = $redis->hGet('aliase', mb_strtoupper($user));
        return $redis->sRandMember("user:{$uid}:alias");
      } else return $user;
    }
    
    public function get_nick($user) {
      global $redis;
      if (empty($user)) return false;
      else if ($redis->status()) {
        $uid = $redis->hGet('aliase', mb_strtoupper($user));
        return $redis->hGet("user:{$uid}:data", 'name');
      } else return $user;
    }
  
    public function is_op_user($user) {
      global $redis;
      if (empty($user)) return false;
      if (!$redis->status()) return ($user == $this->owner) ? true : false;
      $roles = $redis->hKeys("alliance:{$this->ally_id}:roles");
      sort($roles);
      $_op = array_slice($roles, 0, 3);
      $alliance_key = "alliance:{$this->ally_id}";
      if ($redis->sIsMember("{$alliance_key}:member", $user)) {
        $uid = $redis->hGet('aliase', mb_strtoupper($user));
        if (in_array($redis->hGet("user:{$uid}:data", 'role'), $_op)) return true;
        else return ($user == $this->owner) ? true : false;
      }
      else return ($user == $this->owner) ? true : false;
    }
    
    public function get_role($role) {
      global $redis;
      if (!$redis->status()) return false;
      $alliance_key = "alliance:{$this->ally_id}";
      return $redis->hGet("{$alliance_key}:roles", $role);
    }
    
    public function get_access($user, $rights = 63) {
      global $redis;
      if (empty($user)) return false;
      if (!$redis->status()) return ($user == $this->owner) ? true : false;
      $alliance_key = "alliance:{$this->ally_id}";
      if ($redis->sIsMember("{$alliance_key}:member", $user)) {
        $uid = $redis->hGet('aliase', mb_strtoupper($user));
        $role = $redis->hGet("user:{$uid}:data", 'role');
        $roles_min = min($redis->hKeys("{$alliance_key}:roles")) -1;
        return pow(2, ($role - $roles_min)) & $rights;
      }
      return false;
    }
    
    public function is_owner($user) {
      return ($user == $this->owner) ? true : false;
    }
    
    public function setDebug($debug) {
      $this->debug = (bool) $debug;
      if (@$this && $this->lou)
        $this->lou->setDebug($debug);
    }
    
    public function debug($message) {
      if ($this->debug) {
        echo date("[d/m @ H:i:s]") . trim($message) . "\n\r";
        $this->log($message);
      }
    }
    
    public function log($message) {
      if(@$this && !$this->logging)
        return;
      else if (@$this) {
        $line = date("[d/m @ H:i:s]") . trim($message) . "\n";  
        error_log($line, 3, LOG_FILE);
      }
      else if (CLI)
        fwrite(STDOUT, $message);
      else echo $message;
    }
    
    public function reload() {
      $this->stop = true;
      if($this->load_hooks(true)) $this->stop = false;
      return true;
    }
    
    private function load_hooks($reload = false) {
      $dirh = opendir(FNC_DATA);
      while ($file = readdir($dirh)) {
        if (substr($file, -4) == ".php") {
          if ($reload) $this->log("Reload hooks: ".$file);
          else $this->log("Load hooks: ".$file);
          include(FNC_DATA . $file);
        }
      }
      closedir($dirh);
      return true;
    }
}
// do not change from here
LoU_Bot::log("Start...\r\n");
LoU_Bot::log('Redis ('.REDIS_DB.') '.($redis && $redis->status() ? 'works well' : 'don\'t work')."\r\n");
if ($redis) {
  if (!($redis_db_server = $redis->get("server:url"))) {
    // first time startup, set the proper server url
    $redis_db_server = BOT_SERVER;
    $redis->set("server:url", $redis_db_server);
  } 
  if ($redis_db_server != BOT_SERVER && !$_ARG->force) die('LoU world mishmash: please change db or force it:' . REDIS_DB); 
}
$bot = new LoU_Bot;
$bot->run();
?>