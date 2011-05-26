<?php
/*
PHPLoU-bot - an LordOfUltima bot writen in PHP
Copyright (C) 2011 Roland Braband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/
class RedisWrapper {
	// singleton instance 
	private static $instance;
	// redis instance
	private $redis;
	// redis connect status
	private $connect;

	// private constructor function 
	// to prevent external instantiation 
	private function __construct() { 
		if (class_exists('Redis')) {
			$this->redis = new Redis(); // needs https://github.com/nicolasff/phpredis
			$this->connect = $this->redis->connect(REDIS_CONNECTION);
			$this->redis->setOption(Redis::OPT_PREFIX, REDIS_NAMESPACE);
		}
	} 

	// getInstance method 
	public static function getInstance() { 
		if(!self::$instance) {
			self::$instance = new self(); 
		} 
		return self::$instance; 
	} 

	//... 
	
	// Call a dynamically wrapper...
	public function __call($method, $args) { 
		if(method_exists($this->redis, $method)) { 
			return call_user_func_array(array($this->redis, $method), $args); 
		} else { 
			return false;
		} 
	}

	// return an error
	public function status() {
		return ($this->connect && $this->PING() == '+PONG') ? true : false;
	}
}

// get instance of redis db
$redis = RedisWrapper::GetInstance();
echo 'Redis '.($redis->status() ? 'works well' : 'don\'t work')."\n";
?>