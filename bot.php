<?php
/*
PHPLoU_bot - an LoU bot writen in PHP
Copyright (C) 2011 Roland Braband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/
echo "Start...\r\n";
 
// include main files
include_once('config.php');
include_once('lou.php');
include_once('redis.php');

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
  
  private function setRules() {
    $this->timeout = intval(@$this->rules['timeout']);
    $this->fuzzyit = (@$this->rules['fuzzyit']) ? true : false;
    $this->schedule = intval(@$this->rules['schedule']);
    $this->randomice = (@$this->rules['randomice']) ? true : false;
    $this->spamsafe = (@$this->rules['spamsafe']) ? true : false;
  }
  
  public function update(SplSubject $subject) {
    global $bot;
    $bot->log("Category: ".$this->name);
    $return = $this->checkAccess($subject);
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
      $redis->EXPIRE($key, $incr);
      return false;
    }
  }

}

class Hook implements SplSubject {
  private $command;
  private $is_command;
  private $regex;
  
  public $name;
  public $func;
  public $category;
  public $input;
  
  protected $observers= array ();
  
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
    if ($this->notify()) return $anonym($subject, $input);
  }
  
  public function getCommand() {
    return $this->command;
  }
  
  public function compCommand($compare) {
		if ($this->isCommand()) {
			if ($compare['command'][0] != PRE) return false;
			else return preg_match($this->regex, substr($compare['command'], 1));
		} else {
			return (preg_match($this->regex, $compare['command']) || preg_match($this->regex, $compare['message']));
		}
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
}

class LoU_Bot implements SplObserver {
    public $ally_name = BOT_ALLY_NAME;
    public $ally_shortname = BOT_ALLY_SHORTNAME;
    public $bot_user_name = BOT_USER_NAME;
    public $server = BOT_SERVER;
    public $email = BOT_EMAIL;
    public $password = BOT_PASSWORD;
    public $owner = BOT_OWNER;
    public $globalchat = false;
    public $getalliance = true;
    
    public $lou;
    public $categories = array();
    
    private $hooks = array(GLOBALIN => array(), SYSTEMIN => array(), ALLYIN => array(), PRIVATEIN => array(), PRIVATEOUT => array(), ALLIANCE => array(), USER => array());
    private $privhooks = array();
    private $allyhooks = array();
    
    private $stop = false;
    private $ally_id;
    private $bot_user_id;
    private $debug = false;
    
    public function run() {
      $this->lou = LoU::factory($this->server,
                                $this->email,
                                $this->password);
      $this->add_category('default', array('humanice' => true), PUBLICY);
      $this->load_hooks();
      $this->lou->attach($this);
      if ($this->globalchat) $this->lou->set_global_chat();
      while ($this->lou->isConnected()) {
        $chat = $this->lou->get_chat();
        if ($this->getalliance) $alliance = $this->lou->get_alliance();
        if (!$chat || ($this->getalliance && !$alliance)) break;
        usleep(POLLTRIP * 1000);
      }
      $this->log("Terminated!");
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
		
		public function add_msg_hook($msg_hook, $command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
      $_channels = array(ALLYIN => 'add_allymsg_hook', PRIVATEIN => 'add_privmsg_hook', PRIVATEOUT => 'add_provmsg_hook', GLOBALIN => 'add_globlmsg_hook');
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
		
		public function add_alliance_hook($command, $name, $function, $category = 'alliance') {
      $this->hooks[ALLIANCE][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }
		
		public function add_system_hook($command, $name, $function, $category = 'system') {
      $this->hooks[SYSTEMIN][md5($name)] = Hook::factory(trim($command),
                                              $name,
                                              false,
                                              null,
                                              $function,
                                              $this->get_category($category));
    }

    public function update(SplSubject $subject) {
      while($this->stop) {
        $this->log("Wait for reload!");
        usleep(25 * 1000);
      }
      $this->log("FireEvents :)");
      $input = $subject->note;
      print_r($input);
      switch($input['type']) {
        case CHAT:
          foreach (@$this->hooks[$input['channel']] AS $hook) {
            if ($hook->compCommand($input)) {
              $hook->callFunction($this, $input);
            }
          }
          break;
        case ALLIANCE:
          if($input['name'] == $this->ally_name) $this->ally_id = $input['id'];
          foreach (@$this->hooks[$input['type']] AS $hook) {
            $hook->callFunction($this, $input);
          }
          break;
        case USER:
          if($input['name'] == $this->bot_user_name) $this->bot_user_id = $input['id'];
          foreach (@$this->hooks[$input['type']] AS $hook) {
            $hook->callFunction($this, $input);
          }
          break;
      }
    }
    
    public function add_privmsg($message, $user) {
      $this->lou->privmsg($message, $user);
    }
    
    public function add_globlmsg($message) {
      $this->lou->globlmsg($message);
    }
    
    public function add_allymsg($message) {
      $this->lou->allymsg($message);
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
    
    public function is_himself($name) {
      return (mb_strtoupper($name) == mb_strtoupper($this->bot_user_name))? true : false;
    }
    
    public function is_ally_user($user) {
      global $redis;
      if (empty($user)||!$redis->status()) return false;

      $alliance_key = "alliance:{$this->ally_id}";
      if ($redis->SISMEMBER("{$alliance_key}:member", $user)) return true;
      else {
        $alias = $redis->HGET('aliase', mb_strtoupper($user));
        if ($redis->HGET("user:{$alias}:data", 'alliance') == $this->ally_id) return true;
        else return false;
      }
    }
    
    public function get_user_id($user) {
      global $redis;
      if (empty($user)||!$redis->status()) return false;
      return $redis->HGET('aliase', mb_strtoupper($user));
    }
    
    public function get_random_nick($user) {
      global $redis;
      if (empty($user)) return false;
      else if ($redis->status()) {
        $uid = $redis->HGET('aliase', mb_strtoupper($user));
        return $redis->SRANDMEMBER("user:{$uid}:alias");
      } else return $user;
    }
	
    public function is_op_user($user) {
      return ($user == $this->owner) ? true : false; //later implemented
    }
		
    public function is_owner($user) {
      return ($user == $this->owner) ? true : false;
    }
    
    public function log($message) {
      $this->lou->output($message);
    }
    
    public function reload() {
      $this->stop = true;
      if($this->load_hooks()) $this->stop = false;
      return true;
    }
    
    private function load_hooks() {
      $dirh = opendir(FNC_DATA);
      while ($file = readdir($dirh)) {
        if (substr($file, -4) == ".php") {
          include(FNC_DATA . $file);
        }
      }
      closedir($dirh);
      return true;
    }
}
// do not change from here
$bot = new LoU_Bot;
$bot->run();
?>