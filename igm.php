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
  public $folders  = array();
  public $messages  = array();
  
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
  
  public function get_folders() {
    $this->doIGMgetFolders();
    $folders = (!$this->error) ? @$this->stack : null;
    if(is_array($folders)) {
      $this->folders = $this->analyse_folders($folders);
      $this->note = $this->folders;
      $this->output("LoU get IGM folders");
      $this->notify();
      return true;
    }
    return false;
  }
  
  public function get_folder_id_by_type($type) {
    if ($this->get_folders()) {
      if (is_array($this->folders['data'])) foreach($this->folders['data'] as $folder) {
        if ($folder['type'] == $type) return $folder['id'];
      }
    }
    return false;
  }
  
  public function exist_folder_id($folder_id) {
    if (empty($this->folders)) $this->get_folders();
    if ($this->folders['data'][$folder_id]['id'] == intval($folder_id)) return true;
    else return false;
  }
  
  public function get_all_folders() {
    if ($this->get_folders()) {
      if (is_array($this->folders)) {
        return $this->folders['data'];
      }
    }
    return false;
  }
  
  public function get_folder_message_id_by_subject_exp($folder_id, $expression) {
    $result = array();
    if ($this->get_folder_messages($folder_id)) {
      if (is_array($this->messages[$folder_id])) foreach($this->messages[$folder_id]['data'] as $message) {
        if (preg_match($expression, $message['subject'])) $result[] = $message['id'];
      }
    }
    return $result;
  }
  
  public function get_folder_message_id_by_body_exp($folder_id, $expression) {
    $result = array();
    if ($this->get_folder_messages($folder_id)) {
      if (is_array($this->messages[$folder_id])) foreach($this->messages[$folder_id]['data'] as $message) {
        if (preg_match($expression, $message['body'])) $result[] = $message['id'];
      }
    }
    return $result;
  }
  
  public function get_folder_message_by_id($folder_id, $id) {
    if ($this->get_folder_messages($folder_id)) {
      if (is_array($this->messages[$folder_id])) {
        return $this->messages[$folder_id]['data'][$id];
      }
    }
    return false;
  }
  
  public function get_all_folder_messages($folder_id, $start = 0, $end = -1) {
    if ($this->get_folder_messages($folder_id, $start, $end)) {
      if (is_array($this->messages[$folder_id])) {
        return $this->messages[$folder_id]['data'];
      }
    }
    return false;
  }
  
  public function get_folder_messages($folder_id, $start = 0, $end = -1) {
    $this->doGetIGMmessageCount($folder_id);
    $count = (!$this->error && $this->stack) ? @$this->stack : null;
    $end = ($end === -1) ? $count - 1 : (($end < $count) ? $end : $count - 1);
    $max = $end + 1;
    if($count >= 1) {
      if ($count - ($end - $start) >= 1) {
        $this->folders['data'][$folder_id]['count'] = $count;
        $this->debug("LoU call {$max}/{$count} messages for folder {$this->folders['data'][$folder_id]['type']}");
        $this->doInfoFolderMessages($start, $end, $folder_id);
        $messages = ($this->stack) ? @$this->stack : null;
        if(is_array($messages)) {
          $this->messages[$folder_id] = $this->analyse_messages($messages);
        }
      }
      $this->note = $this->messages[$folder_id];
      $this->debug("LoU get {$max}/{$count} messages for folder {$this->folders['data'][$folder_id]['type']}");
      $this->notify();
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
  
  public function doIGMgetFolders() {
    $d = array(
        "session"   => $this->session
    );
    $this->get("IGMGetFolders", $d);
  }

  public function doIGMGetMessage($id) {
    $d = array(
        "session"   => $this->session,
        "id"        => $id
    );
    $this->post("IGMGetMsg", $d);
  }
  
  public function doInfoFolderMessages($start, $end = 99, $folder, $sort = 3, $ascending = false, $direction = false) {
    $d = array(
        "ascending" => $ascending,
        "direction" => $direction,
        "end"       => $end,
        "session"   => $this->session,
        "folder"    => $folder,
        "sort"      => $sort,
        "start"     => $start
    );
    $this->get("IGMGetMsgHeader", $d);
  }
  
  private function analyse_messages($messages) {
    
    foreach($messages as $header) {
      $this->doIGMGetMessage($header['i']);
      $message = ($this->stack) ? @$this->stack : null;
      $items[$header['i']] = array(
        'send'       => floor($header['d']/1000),
        'from'       => $header['f'],
        'from_id'    => $header['fi'],
        'id'         => $header['i'],
        'read'       => $header['r'],
        'subject'    => $header['s'],
        'to'         => $header['t'],
        'to_id'      => $header['ti'],
        'body'       => $message
      );
    }                  
    return array('type' => IGMMESSAGE, 'data' => $items);
  }
  
  public function doGetIGMmessageCount($folder) {
    $d = array(
        "session"   => $this->session,
        "folder"    => $folder
    );
    $this->post("IGMGetMsgCount", $d);
  }
  
  private function analyse_folders($data) {
    $items = array();
    if (is_array($data)) foreach($data as $item) {        
      $this->doGetIGMmessageCount($item['i']);
      $count = (!$this->error && $this->stack) ? @$this->stack : null;
      $folder = Igm::prepare_folder($item);
      $items[$item['i']] = array(
        'id'         => $item['i'],
        'name'       => $folder['name'],
        'type'       => $folder['type'],
        'count'      => $count
      );       
    }

    return array('type' => IGMFOLDER, 'data' => $items);
  }
  
  static function prepare_folder($data) {
    global $_GAMEDATA;
    $type = IGMUNKNOWN;
    switch($data['n']) {
      case '@In': 
        $type = IGMIN;
        $name = $_GAMEDATA->translations['tnf:inbox'];
        break;
      case '@Out':
        $type = IGMOUT;
        $name = $_GAMEDATA->translations['tnf:outbox'];
        break;
    }
    return array('type' => $type, 'name' => $name);
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