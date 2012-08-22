<?php
 // include main files
 include_once($_SERVER['ENV']['LIB'].'config.php');
 include_once($_SERVER['ENV']['LIB'].'redis.php');
 $types = array('user', 'alliance');
 $type = (in_array($_GET['type'], $types)) ? $_GET['type'] : 'user'; //die('no input type spezified!');
 
 switch($type) {
  case 'user':
    $users = $redis->hkeys("aliase");
    // filter by term
    $users = array_filter($users, function ($item) {
      return preg_match('/'.trim($_GET['term']).'/i', $item);
    });
    header('Content-type: application/json');
    echo trim(json_encode(array_values($users)));
    break;
  case 'alliance':
    $alliances = $redis->hkeys("alliances");
    // filter by term
    $alliances = array_filter($alliances, function ($item) {
      return preg_match('/'.trim($_GET['term']).'/i', $item);
    });
    header('Content-type: application/json');
    echo trim(json_encode(array_values($alliances)));
    break;
  }
?>