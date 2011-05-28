<?php
error_reporting(E_ALL);
// bot configuration
define('BOT_ALLY_NAME', 'YouAlliance');
define('BOT_ALLY_SHORTNAME', 'YouAlliance');
define('BOT_USER_NAME', 'BotName');
define('BOT_EMAIL', 'botemail@whatever.com');
define('BOT_PASSWORD', 'botpassword');
define('BOT_OWNER', 'YourName');
define('BOT_SERVER', 'http://prodgame12.lordofultima.com/25/'); // your world server!
define('BOT_LANG','de'); // your prefered language for login
// database
define('REDIS_CONNECTION', '/tmp/redis.socket'); // localhost:6379 or socket
define('REDIS_NAMESPACE', 'lou:'); // use custom prefix on all keys
// time settings
date_default_timezone_set("UTC"); // server default timezone
// extension configuration
define('ALICE_ID', ''); // need an published alicebot id  
// prefix for commands to the bot
define('PRE','!');
//
// after this, no changes needed!
//
// features like spam
define('SPAMTTL', 15);
define('POLLTRIP', 1000);
// log and directory settings
define('BOT_PATH',$_SERVER['PWD'].'/');
define('LOG_PATH',BOT_PATH.'logs/');
define('LOG_FILE',LOG_PATH.'log.txt');
define('PERM_DATA',BOT_PATH.'perm_data/');
define('FNC_DATA',BOT_PATH.'bot_functions/');
// shorthands
define('PUBLICY', 'PUBLICY');
define('OPERATOR', 'OPERATOR');
define('OWNER', 'OWNER');
define('SENDER','s');
define('CHANNEL','c');
define('MESSAGE','m');
define('ALLYIN','ALLYIN');
define('PRIVATEIN','PRIVATEIN');
define('PRIVATEOUT','PRIVATEOUT');
define('GLOBALIN','GLOBALIN');
define('SYSTEMIN','SYSTEMIN');
define('ACCOUNT', 'A');
define('SYSTEM', '@');
define('CHAT', 'CHAT');
define('ALLIANCE', 'ALLIANCE');
define('USER', 'USER');
?>