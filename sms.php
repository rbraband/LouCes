<?php
/*
PHPLoU-bot - an LordOfUltima bot writen in PHP
Copyright (C) 2011 Roland Braband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/
class SMSWrapper {
  // singleton instance 
  private static $instance;
  // sms instance
  private $sms;


  // private constructor function 
  // to prevent external instantiation 
  private function __construct() {
    global $_ARG;
    
    if (class_exists('SMSExpertSender')) {
      $this->sms = new SMSExpertSender(); // needs http://www.sms-expert.de/sms-gateway
      $this->sms->setSMSType('standard');
      $this->sms->setSMSUser(SMS_USER);
      $this->sms->setSMSPassword(SMS_PASSWORD);
      $this->sms->setDebugMode($_ARG->debug);
      if(defined('SMS_EXPERT_SENDER')) 
        $this->sms->setSender(SMS_EXPERT_SENDER);
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
    if(method_exists($this->sms, $method)) { 
      return call_user_func_array(array($this->sms, $method), $args); 
    } else { 
      return false;
    } 
  }

  // send sms
  public function sendSMS($receiver, $message, $datetime = null) {
    try{
      
      $this->sms->setReceiver(self::formatNr($receiver));
      $this->sms->setMessage($message);
      if (is_array($datetime) && !empty($datetime)) {
        $this->sms->setSendDateTime($datetime['year'] ,$datetime['month'], $datetime['day'], $datetime['hour'], $datetime['minute']);
      }
      $this->sms->send();
      $code = $this->sms->getResponseStatusCode();
      if ($code == 200) {
        return array('code'   => $this->sms->getResponseStatusCode(),
                     'text'   => $this->sms->getResponseStatusText(),
                     'id'     => $this->sms->getResponseMessageID(),
                     'cost'   => $this->sms->getResponseCost(),
                     'error'  => false);
      } else {
        $line = trim(date("[d/m @ H:i:s]") . "SMS Error (" . $this->sms->getResponseStatusCode() . "): " . $this->sms->getResponseStatusText()) . "\n";  
        error_log($line, 3, SMS_LOG_FILE);
        return array('code'   => $this->sms->getResponseStatusCode(),
                     'text'   => $this->sms->getResponseStatusText(),
                     'error'  => true);
      }
    }
    catch (SMSExpertSenderException $e){
      $line = trim(date("[d/m @ H:i:s]") . "SMS SenderException: " . $e->getMessage()) . "\n";  
      error_log($line, 3, SMS_LOG_FILE);
      return array('text'  => $e->getMessage(),
                   'code'  => $e->getCode(),
                   'error' => true);
    }
    catch (Exception $e){
      $line = trim(date("[d/m @ H:i:s]") . "SMS Exception: " . $e->getMessage()) . "\n";  
      error_log($line, 3, SMS_LOG_FILE);
      return array('text'  => $e->getMessage(),
                   'code'  => $e->getCode(),
                   'error' => true);
    }
  }
  
  public function sendExpertSMS($receiver, $message, $region, $datetime = null) {
    try{
      $this->sms->setSMSType('expert');
      $this->sms->setReceiver(self::formatIntNr($receiver, $region));
      $this->sms->setMessage($message);
      if (is_array($datetime) && !empty($datetime)) {
        $this->sms->setSendDateTime($datetime['year'] ,$datetime['month'], $datetime['day'], $datetime['hour'], $datetime['minute']);
      }
      $this->sms->send();
      $code = $this->sms->getResponseStatusCode();
      if ($code == 200) {
        return array('code'   => $this->sms->getResponseStatusCode(),
                     'text'   => $this->sms->getResponseStatusText(),
                     'id'     => $this->sms->getResponseMessageID(),
                     'cost'   => $this->sms->getResponseCost(),
                     'error'  => false);
      } else {
        return array('code'   => $this->sms->getResponseStatusCode(),
                     'text'   => $this->sms->getResponseStatusText(),
                     'error'  => true);
      }
    }
    catch (SMSExpertSenderException $e){
      return array('text'  => $e->getMessage(),
                   'code'  => $e->getCode(),
                   'error' => true);
    }
    catch (Exception $e){
      return array('text'  => $e->getMessage(),
                   'code'  => $e->getCode(),
                   'error' => true);
    }
  }
  
  private static function formatNr($number) {
    if (strpos((string)$number, '0') === 0 ) $number = substr((string)$number, 1);
    if (strpos((string)$number, '+') === 0 ) $number = substr((string)$number, 1);
    if (strpos((string)$number, (string)SMS_REGION) !== 0 ) $number = SMS_REGION . $number;
    return intval($number);
  }
  
  private static function formatIntNr($number, $region) {
    if (strpos((string)$number, '0') === 0 ) $number = substr((string)$number, 1);
    if (strpos((string)$number, '+') === 0 ) $number = substr((string)$number, 1);
    if (strpos((string)$number, (string)$region) !== 0 ) $number = $region . $number;
    return intval($number);
  }
}

/**
 * Own exception for the SMSExpertSender
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
class SMSExpertSenderException extends Exception{

  function __construct($strMessage){
    parent::__construct($strMessage);
  }
}
/**
 * Sends SMS via the SMS-Gateway of SMS-Expert
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
class SMSExpertSender{
  /**
   * @var string $user
   */
  private $user = '';
  /**
   * @var string $gateway_password
   */
  private $gateway_password = '';
  /**
   * @var string $sendmode Possible values for sendmode: curl, socket, file
   */
  private static $sendmode = 'curl';
  /**
   * @var boolean $debug enables or disables debugging output
   */
  private $debug = false;

  private static $gateway_protocol = 'https://';
  private static $gateway_host = 'gateway.sms-expert.de';
  private static $gateway_port = 443;
  private static $gateway_urlpath = '/send/';
  private static $version = 1.0;

  private static $typelist = array('standard','expert');
  private $type = null;
  private $receiver = null;
  private $sender = null;
  private $message = null;
  private $timestamp = null;
  private $responseStatusCode = null;
  private $responseStatusText = null;
  private $responseMessageID = null;
  private $responseCost = null;

  /**
   *  Sets the Debug-Mode.
    */
  public function setDebugMode($debug = false){
    $this->debug = $debug;
  }
  
  /**
   *  Sets the user of the SMS-Gateway
   *  @param string $user Name of the User.
    */
  public function setSMSUser($user){
    $this->user = $user;
  }
  
  /**
   *  Sets the password of the SMS-Gateway
   *  @param string $password Password of the User.
    */
  public function setSMSPassword($password){
    $this->gateway_password = $password;
  }
  
  /**
   *  Sets the type of the SMS
   *  @param string $type Type of the SMS. Possible values "standard" or "expert"
    */
  public function setSMSType($type){
    $type = strtolower($type);
    if (!in_array($type,self::$typelist)) {
      throw new SMSExpertSenderException('Ungültiger SMS-Typ');
    }
    $this->type = $type;
  }

  /**
     * Gets the type of the sms
     * @return string Type of the SMS
     */
  public function getSMSType(){
    return $this->type;
  }

  /**
    * Sets the phone number of the receiver
    * @param integer $receiver Number in international format WITHOUT leading + or 00
    * @throws SMSExpertSenderException
     */
  public function setReceiver($receiver){
    $regex = '/[1-9]{1}[0-9]{5,15}/i';
    if (!preg_match($regex,$receiver)) {
      throw new SMSExpertSenderException('Ungültig Empfänger');
    }
    $this->receiver = $receiver;
  }

  /**
   *  Returns the phone number of the receiver
   *  @return integer Phone number of the receiver
   */
  public function getReceiver(){
    return $this->receiver ;
  }

  /**
   * Sets the sender phone number or a text
   * @param string $sender Sender phone number in international format WITHOUT leading + or 00. From 6 to 16 digits possible. For a text as sender up to 11 characters possible.
   * @throws SMSExpertSenderException
   */
  public function setSender($sender){
    $regexNum = "/[0-9]+/i";
    $regexNumOK = "/[0-9]{6,16}/i";
    $regexStrOK = "/[\\S]{1,11}/i";
    // sender is a number
    if(preg_match($regexNum,$sender)){
      if (!preg_match($regexNumOK,$sender)) {
        throw new SMSExpertSenderException('Ungültige Absender-Nummer');
      }
    }
    else{
      if (!is_numeric($sender) && mb_strlen($sender) > 11) {
        throw new SMSExpertSenderException('Ungültiger Absender-Text');
      }
    }
    $this->sender = $sender;
  }

  /**
   * Returns the sender of the SMS
   * @return string Sender of the SMS
   */
  public function getSender(){
    return $this->sender;
  }

  /**
   * Sets the message text.
   * @param string $message The message text. Up to 1530 characters possible.
   * @throws SMSExpertSenderException
   */
  public function setMessage($message){
    $len = mb_strlen($message);
    if ($len < 1 || $len > 1530) {
      throw new SMSExpertSenderException('Ungültig  Nachricht');
    }
    $this->message = mb_convert_encoding($message, "UTF-8", mb_detect_encoding($message, "UTF-8, ISO-8859-1, ISO-8859-15", true));
  }

  /**
   * Returns the message text.
   * @return string The message text
   */
  public function getMessage(){
    return $this->message;
  }

  /**
    * Sets the date and the time for a timeshifted SMS. DON'T call these function if you want send the SMS immediately.
    *   @param integer $year Year (4 digits, YYYY)
    *  @param integer $month Month (2 digits, mm)
    *  @param integer $day Day (2 digits, dd)
    *  @param integer $hour Hour (2 digits, HH)
    *  @param integer $minute Minute (2 digits, ii)
    *  @throws SMSExpertSenderException
   */
  public function setSendDateTime($year, $month, $day, $hour, $minute){
    if (!checkdate($month, $day, $year)) {
      throw new SMSExpertSenderException('Ungültiges Datum für den Versandzeitpunkt eingegeben.');
    }
    if ($hour < 0 || $hour > 23) {
      throw new SMSExpertSenderException('Ungültige Stunde für den Versandzeitpunkt eingegeben.');
    }
    if ($minute < 0 || $minute > 59) {
      throw new SMSExpertSenderException('Ungültige Minute für den Versandzeitpunkt eingegeben.');
    }
    $timestamp_send = mktime($hour,$minute,0,$month,$day,$year);
    $timestamp_now = time();
    if (($timestamp_now + 300) > $timestamp_send) {
      throw new SMSExpertSenderException('Versandzeitpunkt befindet sich in der Vergangenzeit oder innerhalb der nächsten 5 Minuten.');
    }
    $this->timestamp = $timestamp_send;
  }

  /**
   * Returns the date and time for a timeshifted SMS
   * @parma string $format Date format
   * @return string Date and Time
   * @see http://php.net/manual/de/function.date.php
   */
  public function getSendDateTime($format = "YYYY-mm-dd HH:ii"){
    $time_string = date($format,$this->timestamp);
    return $time_string;
  }

  /**
   * Checks and sends the SMS
     * @throws SMSExpertSenderException
   */
  public function send(){
    if (is_null($this->type) || is_null($this->receiver) || is_null($this->message)) {
      throw new SMSExpertSenderException('Nicht alle notwendigen Parameter sind gesetzt.');
    }
    if (is_null($this->sender) && $this->type == 'expert') {
      throw new SMSExpertSenderException('Der notwendige Parameter "Absender" für den Typ "Expert" ist nicht gesetzt.');
    }
    $status = FALSE;
    if (self::$sendmode == 'curl') {
      $status = $this->sendModeCurl();
    }
    if (self::$sendmode == 'socket') {
      $status = $this->sendModeSocket();

    }
    if (self::$sendmode == 'file') {
      $status = $this->sendModeFile();
    }
    if (!$status) {
      throw new SMSExpertSenderException('SMS-Versand fehlgeschlagen! SMS-Gateway nicht erreichbar
        oder der gewählte sendmode wird nicht unterstützt.');
    }
  }

  /**
   *  Sends the SMS via Curl (POST)
   *  @link http://de.php.net/manual/de/book.curl.php
   */
  private function sendModeCurl(){
    $cu = curl_init();
    if (!$cu) {
      return false;
    }
    $url = self::$gateway_protocol . self::$gateway_host . self::$gateway_urlpath;
    if($this->debug){
      echo "URL: $url\n";
      echo "Port: " . self::$gateway_port . "\n";
    }
    curl_setopt($cu, CURLOPT_URL, $url);
    curl_setopt($cu, CURLOPT_PORT, self::$gateway_port);
    curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cu, CURLOPT_USERAGENT, 'SMSExpertSenderPHP v' . number_format(self::$version,2,'.',','));
    curl_setopt($cu, CURLOPT_POST, true);
    curl_setopt($cu, CURLOPT_POSTFIELDS, $this->getRequestData());
    $response = curl_exec($cu);
    $this->readResponse($response);
    curl_close($cu);
    return true;
  }

   /**
    * Sends the SMS via Socket (POST)
     * @link http://de.php.net/manual/de/function.fsockopen.php
   * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
   */
  private function sendModeSocket(){
    if (self::$gateway_protocol == 'https://') {
      $protocol = 'ssl://';
    }
    elseif(self::$gateway_protocol == 'http://'){
      $protocol = 'tcp://';
    }
    else{
      return false;
    }
    $hostname = $protocol . self::$gateway_host;
    if($this->debug){
      echo "Hostname: $hostname\n";
      echo "Port: " . self::$gateway_port . "\n";
      echo "Path: " . self::$gateway_urlpath  . "\n";
    }
    // Verbindung öffnen
    $socket = fsockopen($hostname, self::$gateway_port, $errno, $errstr,2);
    if (!$socket) { // Keine Verbindung möglich
      return false;
    }
    // Header erstellen
    $request = $this->getRequestData();
    $header  = 'POST ' . self::$gateway_urlpath . " HTTP/1.1\r\n";
    $header .= 'Host: ' . self::$gateway_host  . "\r\n";
    $header .= 'User-Agent: SMSExpertSenderPHP v' . number_format(self::$version,2,'.',',') . "\r\n";
    $header .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
    $header .= 'Content-Length: ' . strlen($request) . "\r\n";
    $header .= 'Connection: close' . "\r\n\r\n";
    $header .= $request. "\r\n";
      fputs($socket, $header); // Header senden
    $response = null;
     while(!feof($socket))
      {
        $response[] = fgets($socket,4096); // Antwort empfangen
      }
      fclose($socket); // Verbindung schließen
    $content = $this->getContentOfHTTPResponse($response);
    $this->readResponse($content);
      return true;
  }

  /**
   * Extracts the content of the HTTP-Response. Needed only for the sendmode "socket".
   * @return string Content of the HTTP-Response.
   */
  private function getContentOfHTTPResponse(Array $response){
    $content = '';
    $content_start = null;
    for($i = 0;$i < sizeof($response);$i++){
      if (is_null($content_start) && $response[$i] == "\r\n" ) {
        $content_start = $i + 1;
      }
      if (!is_null($content_start) && $i >= $content_start) {
        $content .= $response[$i];
      }
    }
    return $content;
  }

  /**
   * Sends the SMS via file (GET)
   * @link http://www.php.net/manual/de/function.file-get-contents.php
   */
  private function sendModeFile(){
    $url  = self::$gateway_protocol . self::$gateway_host . ':' . self::$gateway_port;
    $url .= self::$gateway_urlpath . '?' . $this->getRequestData();
    if($this->debug){
      echo "URL: $url\n";
    }
    $response = file_get_contents($url);
    $this->readResponse($response);
    return true;
  }

  /**
   * Returns urlencoded name-value pairs for the HTTP-Request
   * @return string Urlencoded name-value pairs
   */
  private function getRequestData(){
    $request  = 'user=' . urlencode($this->user) . '&';
    $request .= 'type=' . urlencode($this->type) . '&';
    $request .= 'receiver=' . urlencode($this->receiver) . '&';
    if (!is_null($this->sender)) {
        $request .= 'sender=' . urlencode($this->sender) . '&';
    }
    $request .= 'message=' . urlencode($this->message). '&';
    if (!is_null($this->timestamp)) {
      $request .= 'timestamp=' . urlencode($this->timestamp) . '&';
    }
    $request .= 'hash=' . urlencode($this->getHash());
    if ($this->debug) {
      echo 'Request data: ' . $request ."\n";
    }
    return $request;
  }

  /**
    * Returns the MD5 hash for the SMS
    * Hash Source: User|GatewayPassword|Type|Sender|Receiver|Message|Timestamp
    * @return string MD5-Hash
     */
  private function getHash(){
    $data  = $this->user . '|' . $this->gateway_password . '|' . $this->type . '|';
    $data .= $this->sender . '|' . $this->receiver . '|' . $this->message;
    $data .= '|' . $this->timestamp;
    if ($this->debug) {
      echo "Hash source: $data\n";
    }
    $hash  = md5($data);
    if ($this->debug) {
      echo "Hash MD5: $hash\n";
    }
    return $hash;
  }

  /**
   *  Reads the XML-Response of the SMS-Gateway
   *  @param string $response The XML-Response of the gateway.
   *  @link http://de3.php.net/manual/de/function.simplexml-load-string.php
   */
  private function readResponse($response){
    $xml_start = stripos($response, '<?xml');
    $xml_end = strrpos($response,'>');
    if ($xml_start === false || $xml_end === false) {
      return false;
    }
    $response =substr($response,$xml_start,($xml_end+1)-strlen($response));
    if ($this->debug) {
      echo 'XML-Response: ' . trim($response) . "\n";
    }
    $xml = simplexml_load_string($response);
    $this->responseStatusCode = (integer)$xml->statusCode[0];
    $this->responseStatusText = (string)$xml->statusText[0];
    $this->responseMessageID  = (integer)$xml->messageId[0];
    $this->responseCost       = (float)$xml->cost[0];
  }

  /**
   * Returns the status code of the response
   * @return integer Status code
   */
  public function getResponseStatusCode(){
    return $this->responseStatusCode;
  }

  /**
   * Returns the status text of the response
   * @return string Status text
   */
  public function getResponseStatusText(){
    return str_replace(chr(13), '', $this->responseStatusText);
  }

  /**
   * Returns the message id of the response
   * @return string Message-ID
   */
  public function getResponseMessageID(){
    return $this->responseMessageID;
  }

  /**
   * Returns the cost of the response
   * @return double Cost in EUR
   */
  public function getResponseCost(){
    return $this->responseCost;
  }
}

// get instance of SMS Gateway
$sms = SMSWrapper::GetInstance();
?>