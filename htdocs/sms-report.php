<?php
/**
 * Receives delivery reports of the SMS-Gateway
 *
 * Copyright 2009 SMS-Expert
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @version 1.0
 * @author Bastian Treger
 * @author SMS-Expert
 * @link http://www.sms-expert.de
 * @copyright SMS-Expert 2009
 */

// include main files
include_once('../config.php');
include_once('../redis.php');

/**
 * Returns the MD5 Hash for the SMS-Response
 * @param array $data Array of transmitted data (see above)
 * @param string $password Your Gateway-Password
 */
function getHash(array $data, $password) {
  $hash_source = null;
  foreach ($data as $value){
    $hash_source .= $value  . '|';
  }
  $hash_source .= $password;
  $hash_md5 = md5($hash_source);
  return $hash_md5;
}

$databases = array(2,3,4); 

$data = array('msg_id'    => $_POST['messageId'],
              'status'    => $_POST['dlrStatus'],
              'timestamp' => $_POST['dlrTimestamp']);
              
if (getHash($data, SMS_PASSWORD) == $_POST['hash']) {
// DO something here like sending mail, store in database ...
  $found = false;
  if (!empty($data['msg_id'])) foreach($databases as $database) {
    $redis->SELECT($database);
    if ($redis->EXISTS("sms:send:{$data['msg_id']}")) {
      $send = $redis->HGETALL("sms:send:{$data['msg_id']}");
      if (is_array($send)) {
        $found = true;
        $redis->HMSET("sms:send:{$data['msg_id']}", array(
                      'status'    => $data['status']));
        $redis->SADD("sms:outbound", (string)$data['msg_id']);              
        break;
      } else {
        $line = trim(date("[d/m @ H:i:s]") . "SMS report Error: " . implode(', ', $data)) . "\n";  
        error_log($line, 3, SMS_LOG_FILE);
      }
    }
  } else {
    $line = trim(date("[d/m @ H:i:s]") . "SMS data Error: " . implode(', ', $_POST)) . "\n";  
    error_log($line, 3, SMS_LOG_FILE);
  }
  if ($found) header("HTTP/1.0 200 OK");
  else header("HTTP/1.0 404 Not Found");
} else header("HTTP/1.0 400 Bad Request");
?>