<?php
/*
PHPLoU_bot - an LoU bot writen in PHP
Copyright (C) 2012 Roland Braband / rbraband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define('IGMPART', 2900);

class Igm {
  private $lou;
    
  static function factory($lou) {
        
    // New Igm Object
    $igm = new Igm($lou);
    // Return the object
    return $igm;
  }
  
  public function __construct($lou) {
      $this->lou =& $lou;
  }
  
  public function send($target, $subject, $body, $maxlength = 3000) {
    $length = IGMPART;
    $maxlength = abs((int)$maxlength);
    if(strlen($body) > $maxlength) {
      $texts = array();
      $igm_chunks = explode('***chunk***', wordwrap ($body, $length, '***chunk***'));
      for($i = 0, $parts = count($igm_chunks); $i < $parts; ++$i) {
        $part = $i + 1;
        $texts[$i] = $igm_chunks[$i];
        if ($part != 1) $texts[$i] = "✂ - - - - -\n" . $texts[$i];
        $texts[$i] .= "\n[hr]Teil: [i]{$part}/{$parts}[/i]";
        $subjects = $subject." - Teil: {$part}";
        $this->doSendMsg($target, $subjects, $texts[$i]);
      }
    } else $this->doSendMsg($target, $subject, $body);

    $ok = ($this->stack) ? @$this->stack : null;
    if($ok === true) {
      $this->output("LoU send igm");
      return true;
    }
    return false;
  }
  
  private function doSendMsg($target, $title, $message, $cc = '') {
    $bulk = (count(explode(";", $target)) > 1 || !empty($cc)) ? true : false;
    if ($bulk) {
      $d = array(
        "session"          => $this->session,
        "targets"          => $target,
        "subject"          => $title,
        "ccTargets"        => $cc,
        "body"             => $message
      );
      $this->get("IGMBulkSendMsg", $d);
    } else {
      $d = array(
        "session"          => $this->session,
        "target"           => $target,
        "subject"          => $title,
        "ccTargets"        => "",
        "body"             => $message
      );
      $this->get("IGMSendMsg", $d);
    }
  }
  
  public function __call($name, $args) {
    return call_user_func_array(array($this->lou, $name), $args);
    //return $this->lou->$name($args);
  }
  
  static function __callStatic($name, $args) {
    return call_user_func_array("Lou::$name", $args);
    //return Lou::$name($args);
  }
  
  public function __set($name, $val) {
    $this->lou->$name = $val;
  }
  
  public function __get($name) {
    return $this->lou->$name;
  }
}    
?>