<?php
/*
PHPLoU-bot - an LordOfUltima bot writen in PHP
Copyright (C) 2011 Roland Braband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/
class Cron implements SplSubject {
	public $note;
	
	private $lastExec;

	protected $observers= array ();
	
	const TICK1 	= 'cron.tick_1';
	const TICK5 	= 'cron.tick_5';
	const TICK10 	= 'cron.tick_10';
	const TICK15 	= 'cron.tick_15';
	const TICK20 	= 'cron.tick_20';
	const TICK30 	= 'cron.tick_30';
	const HOURLY 	= 'cron.hourly';
	const DAILY 	= 'cron.daily';
	const WEEKLY 	= 'cron.weekly';
	const MONTHLY = 'cron.monthly';
	const YEARLY 	= 'cron.yearly';
	
	private $crons = array(
		array('cron.hourly', 	'is', 	5),
		array('cron.daily', 	'Gis', 	4),
		array('cron.weekly', 	'wGis', 3),
		array('cron.monthly', 'jGis', 10002),
		array('cron.yearly', 	'zGis', 1)
	);
	
	private $ticks = array(
		array('cron.tick_1', 	'is',			100),
		array('cron.tick_5', 	'is',			500),
		array('cron.tick_10', 'is',			1000),
		array('cron.tick_15', 'is',			1500),
		array('cron.tick_20', 'is',			2000),
		array('cron.tick_30', 'is',			3000),
	);

	static function factory() {
        
			// New Cron Object
			$cron = new Cron;
			
			// Return the object
			return $cron;
	}

	public function check() {
		$return = false;
		$this->lastExec = time();
		foreach($this->crons as $cron) {
			if (abs(date($cron[1], $this->lastExec)) == $cron[2]) {
				$this->note = array('type' => CRON,
														'name' => $cron[0],
														'time' => $this->lastExec
														);
				$this->notify();
				$return = true;
			}
		}
		foreach($this->ticks as $tick) {
			if (!(abs(date($tick[1], $this->lastExec)) % $tick[2])) {
				$this->note = array('type' => TICK,
														'name' => $tick[0],
														'time' => $this->lastExec
														);
				$this->notify();
				$return = true;
			}
		}
		return $return;
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

}
?>