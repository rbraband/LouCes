<?php
// error reporting
error_reporting(E_ALL ^ E_NOTICE);
// determine enviroment
define('CLI', (bool) defined('STDIN'));
// configuration
define('BOT_EMAIL', 'your@email.com');
define('BOT_PASSWORD', 'yourpassword');
define('BOT_OWNER', 'YourInGameNick');
define('BOT_SERVER', 'http://prodgameyx.lordofultima.com/123/'); // your world server!
define('BOT_LANG','de'); // your prefered language for login
// prefix for commands to the bot
define('PRE','!');
// time settings
date_default_timezone_set("Europe/Berlin"); // server default timezone
setlocale(LC_TIME, "de_DE");
setlocale(LC_ALL, 'de_DE@euro', 'de_DE', 'de', 'ge');
//
// after this, no changes needed!
//
// features like spam
define('SPAMTTL', 15);
define('POLLTRIP', 1);
define('SETTLERTTL', 86400);
// ignore
define('IGNORE_PUNISHTTL', 3600);
// stats
define('STATS_URL', 'stats.localhost');
// alice
define('ALICETTL', 3);
define('ALICEID', 'youralice_id'); // need an published alicebot id
// global chat
define('GLOABLCHAT', false);
// fork
define('MAXCHILDS', 5);
// server pain barrier
define('MAX_PARALLEL_REQUESTS', 16);
// forums
define('BOT_STATISTICS_FORUM', 'Stats');
define('BOT_SETTLERS_FORUM', 'Settlers');
define('BOT_REPORTS_FORUM', 'Reports');
define('BOT_BLACK_FORUM', 'BlackBook');
define('BOT_SURVEY_FORUM', 'Survey');
// log and directory settings
define('BOT_PATH',((CLI) ? $_SERVER["PWD"] : $_SERVER["DOCUMENT_ROOT"]).'/');
define('LOG_PATH',BOT_PATH.'logs/');
define('LOG_FILE',LOG_PATH.'log.txt');
define('PERM_DATA',BOT_PATH.'perm_data/');
define('DOKU_DATA',BOT_PATH.'doku_data/');
define('FNC_DATA',BOT_PATH.'bot_functions/');
// lock
define('LOCK_FILE', BOT_PATH.str_replace('.php', '.lock', $_SERVER['PHP_SELF']));
// shorthands
define('SERVER', 'SERVER');
define('PUBLICY', 'PUBLICY');
define('OPERATOR', 'OPERATOR');
define('PRIVACY', 'PRIVACY');
define('OWNER', 'OWNER');
define('SENDER','s');
define('CHANNEL','c');
define('MESSAGE','m');
define('ALLYIN','ALLYIN');
define('PRIVATEIN','PRIVATEIN');
define('PRIVATEOUT','PRIVATEOUT');
define('GLOBALIN','GLOBALIN');
define('SYSTEMIN','SYSTEMIN');
define('OFFICER','OFFICER');
define('ACCOUNT', 'A');
define('LOUACB', 'B');
define('LOUACC', 'C');
define('SYSTEM', '@');
define('UNKOWN', '$');
define('ANONYM', 'ANONYM');
define('CHAT', 'CHAT');
define('ALLIANCE', 'ALLIANCE');
define('IGNORE', 'IGNORE');
define('LISTS', 'LISTS');
define('ATTACK', 'ATTACK');
define('ALLYATT', 'ALLYATT');
define('PLAYER', 'PLAYER');
define('CITY', 'CITY');
define('USER', 'USER');
define('REPORT', 'REPORT');
define('REPORTHEADER', 'REPORTHEADER');
define('SMS', 'SMS');
define('INCOMMING', 'INCOMMING');
define('SIEGE', 'SIEGE');
define('COMMAND', 'COMMAND');
define('EMAIL', 'EMAIL');
define('REDIS', 'REDIS');
define('BOT', 'BOT');
define('CRON', 'CRON');
define('TICK', 'TICK');
define('STATISTICS', 'STATISTICS');
define('STAT_POINTS', 0);
define('STAT_RESSOURCES', 1);
define('STAT_TS', 2);
define('STAT_OFFENCE', 3);
define('STAT_DEFENCE', 4);
define('CONTINENT', 'CONTINENT');
define('RANGE', 'RANGE');
define('KICKED', 'KICKED');
define('CLOSED', 'CLOSED');
define('EMPTY', 'EMPTY');
define('DROPED', 'DROPED');
define('SYS', 'SYS');
define('VERSION', 'VERSION');
define('SCOUT', 1);
define('PLUNDER', 2);
define('ASSAULT', 3);
define('SUPPORT', 4);
define('SIEGE', 5);
define('RAID', 8);
define('SETTLE', 9);
define('BOSS_RAID', 10);
define('CITY_STATE', '0');
define('CASTLE_STATE', '1');
define('PALACE_STATE', '2');
define('WATER_STATE', '1');
// redis database
define('REDIS_CONNECTION', ((CLI) ? '/var/run/redis/redis.sock' : '127.0.0.1')); // localhost or socket
#define('REDIS_CONNECTION', '127.0.0.1'); // localhost only
define('REDIS_NAMESPACE', 'lou:'); // use custom prefix on all keys
define('REDIS_DB', 1);
define('REDIS_LOG_FILE', LOG_PATH.'redis.txt');
// mysql database
#define('MYSQL_CONNECTION', 'localhost'); // localhost only
#define('MYSQL_CONNECTION', ((CLI) ? ':/var/run/mysqld/mysqld.sock' : '127.0.0.1')); // localhost or socket
#define('MYSQL_DB', 'lou');
#define('MYSQL_USER', 'lou-bot');
#define('MYSQL_PWD', '');
// rights
define('ALLOW_SYS',  1);
define('ALLOW_LEAD', 1+2+4);
define('ALLOW_OFF',  1+2+4+8);
define('ALLOW_ALL',  1+2+4+8+16+32);
// sms
define('SMS_USER', ''); // SMS-Gateway User
define('SMS_PASSWORD', ''); // SMS-Gateway Password
define('SMS_EXPERT_SENDER', 'LoU-SMS'); // SMS-Expert Sender
define('SMS_LOG_FILE', LOG_PATH.'sms.txt');
define('SMS_REGION', 49);
define('SMS_SPAMTTL', 30);
define('SMS_KONTINGENT', 10);
define('SMS_SYS',  1);
define('SMS_LEAD', 1+2+4);
define('SMS_OFF',  1+2+4+8);
define('SMS_ALL',  1+2+4+8+16+32);
define('SMS_ALERT_OFF',  0);
define('SMS_ALERT_OWN',  1);
define('SMS_ALERT_ALL',  2);
define('SMS_SHARE_OFF',  0);
define('SMS_SHARE_ON',   1);
define('SMS_STATUS_OPEN', 'OPEN');
define('SMS_STATUS_DELIVERED', 'DELIVERED');
define('SMS_STATUS_TRANSMITTED', 'TRANSMITTED');
define('SMS_STATUS_BUFFERED', 'BUFFERED');
define('SMS_STATUS_NOT_DELIVERED','NOT_DELIVERED');
define('SMS_STATUS_ANSWERED', 'ANSWERED');
// email
define('EMAIL_LOG_FILE', LOG_PATH.'email.txt');
define('EMAIL_SPAMTTL', 30);
define('EMAIL_SYS',  1);
define('EMAIL_LEAD', 1+2+4);
define('EMAIL_OFF',  1+2+4+8);
define('EMAIL_ALL',  1+2+4+8+16+32);
define('EMAIL_ALERT_OFF',  0);
define('EMAIL_ALERT_OWN',  1);
define('EMAIL_ALERT_ALL',  2);
define('EMAIL_SHARE_OFF',  0);
define('EMAIL_SHARE_ON',   1);
define('EMAIL_IS_SENDMAIL', true);
define('EMAIL_STATUS_OPEN',         'OPEN');
define('EMAIL_STATUS_TRANSMITTED',  'TRANSMITTED');
define('EMAIL_STATUS_BUFFERED',     'BUFFERED');
define('EMAIL_STATUS_ANSWERED',     'ANSWERED');
// read argv
function arguments($argv) { 
  $ARG = array();
  if(is_array($argv)) foreach ($argv as $arg) { 
    if (strpos($arg, '--') === 0) { 
      $compspec = explode('=', $arg); 
      $key = str_replace('--', '', array_shift($compspec)); 
      $value = join('=', $compspec); 
      $ARG[$key] = $value; 
    } elseif (strpos($arg, '-') === 0) { 
      $key = str_replace('-', '', $arg); 
      if (!isset($ARG[$key])) $ARG[$key] = true; 
    } 
  } 
  return new ArrayObject($ARG, ArrayObject::ARRAY_AS_PROPS); 
}
$_ARG = arguments($argv);
$_GAMEDATA = new ArrayObject(json_decode(file_get_contents(PERM_DATA . 'game.data.min.' . BOT_LANG), true), ArrayObject::ARRAY_AS_PROPS);
?>