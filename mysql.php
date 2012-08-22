<?php
/*
PHPLoU-bot - an LordOfUltima bot writen in PHP
Copyright (C) 2011 Roland Braband

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/
class MySqlDb {
  // Singleton instance 
  private static $instance;
  var $QUERY = null;  // Query String
  var $RESULT = null; // Query result
  var $db_connection = null; // Database connection string
  var $db_server = null;     // Database server
  var $db_database = null;   // The database being connected to
  var $db_username = null;   // The database username
  var $db_password = null;   // The database password
  var $CONNECTED = false;    // Determines if connection is established
  
  // private constructor function 
  // to prevent external instantiation 
  private function __construct() { 
    $this->NewConnection(MYSQL_CONNECTION, MYSQL_DB, MYSQL_USER, MYSQL_PWD);
  }
  
  // getInstance method 
  public static function getInstance() { 
    if(!self::$instance) {
      self::$instance = new self(); 
    } 
    return self::$instance; 
  } 

  //... 

  /** NewConnection Method
   * This method establishes a new connection to the database. */
  public function NewConnection($server, $database, $username, $password) {
    // Assign variables
    $this->db_server   = $server;
    $this->db_database = $database;
    $this->db_username = $username;
    $this->db_password = $password;
    // Attempt connection
    try {
      // Create connection to MYSQL database
      // Fourth true parameter will allow for multiple connections to be made
      $this->db_connection = mysql_connect($this->db_server, $this->db_username, $this->db_password, true);
      mysql_select_db($this->db_database);
      if (!$this->db_connection) {
        throw new Exception('MySQL Connection Database Error: ' . mysql_error());
      } else {
        $this->CONNECTED = true;
      }
    }
    catch (Exception $e) {
      echo $e->getMessage();
    }
  }
  /** Open Method
   * This method opens the database connection (only call if closed!) */
  public function Open() {
    if (!$this->CONNECTED) {
      try {
        $this->db_connection = mysql_connect($this->db_server, $this->db_username, $this->db_password);
        mysql_set_charset('utf8', $this->db_database);
        mysql_select_db($this->db_database);
        if (!$this->db_connection) {
          throw new Exception('MySQL Connection Database Error: ' . mysql_error());
        } else {
          $this->CONNECTED = true;
        }
      }
      catch (Exception $e) {
        echo $e->GetMessage();
      }
    } else {
      return "Error: No connection has been established to the database. Cannot open connection.";
    }
  }
  /** Close Method
   * This method closes the connection to the MySQL Database */
  public function Close() {
    if ($this->CONNECTED) {
      mysql_close($this->db_connection);
      $this->CONNECTED = false;
    } else {
      echo "Error: No connection has been established to the database. Cannot close connection.";
    }
  }
  /** Query Method
   * This method use sprintf to get query result */
  function Queryf($query) {
    if ($this->CONNECTED) {
      if ($this->CurrentDb != $this->db_database)
        mysql_select_db($this->db_database);
      if (func_num_args() > 1) {
        $args  = func_get_args();
        $query = call_user_func_array("sprintf", $args);
      }
      try {
        $this->QUERY  = $query;
        $this->RESULT = mysql_query($query);
        if (!$this->RESULT) {
          throw new Exception('Error: Query ($query) : ' . mysql_error() . print_r($this));
        } else {
          return $this->RESULT;
        }
      }
      catch (Exception $e) {
        echo $e->GetMessage();
      }
    } else {
      return "Error: No connection has been established to the database. Cannot stat query.";
    }
  }
  /** Get Database name
   * This method return the name of the current open db */
  function CurrentDb() {
    $r = mysql_query("SELECT DATABASE()");
    return mysql_result($r, 0);
  }
}

// get instance of mysql db
$mysql = MySqlDb::getInstance();
?>