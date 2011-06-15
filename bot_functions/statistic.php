<?php
global $bot;
$bot->add_category('statistic', array(), PUBLICY);

$bot->add_cron_event(Cron::HOURLY,							 						// Cron key
										"GetContinentUpdate",                 	// command key
										"LouBot_stats_continent_update_cron", 	// callback function
function ($bot, $data) {
  $bot->lou->get_continents();
}, 'statistic');

$bot->add_statistic_hook("ContinentUpdate",                // command key
                         "LouBot_continent_update",        // callback function
function ($bot, $stat) {
  global $redis;
  if (empty($stat['id'])||$stat['id'] != CONTINENT||!$redis->status()) return;
	if (is_array($stat['data'])) foreach ($stat['data'] as $continent) {
		if ($continent != -1) $update = $redis->SADD("continents", $continent);
		if ($update) $bot->log('Redis update : '.REDIS_NAMESPACE.'continents:'.$continent);
	}
}, 'statistic');
?>