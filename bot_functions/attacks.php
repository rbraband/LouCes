<?php
global $bot;
$bot->add_category('attacks', array(), PUBLICY);

$bot->add_attack_hook("UpdateAttacks",                       // command key
                      "LouBot_alliance_attacks_update",      // callback function
function ($bot, $attacks) {
  global $redis;
  if (empty($attacks['id'])||!$redis->status()) return;
  if (is_array($attacks)) foreach($attacks['data'] as $att) {
    if ($bot->ally_id != $att['source']['ally_id']) { // prevent friendly fire
      $alliance_key = "alliance:{$bot->ally_id}";
      $new = $redis->SETNX("attacks:{$alliance_key}:{$att['id']}", $att['state']);
      $redis->EXPIREAT("attacks:{$alliance_key}:{$att['id']}", $att['eta']);
      // do anything
      if ($att['state'] == INCOMMING && $new) {
        $bot->log("Attack new Id:".$att['id']." ".$att['source']['player_name']." vs. '".$att['target']['city_name']."' State: ".$att['state']." @ " . date('d.m.Y H:i:s', $att['eta']));
      } else {
        $bot->log("Attack update Id:".$att['id']." ".$att['source']['player_name']." vs. '".$att['target']['city_name']."' State: ".$att['state']." @ " . date('d.m.Y H:i:s', $att['eta']));
      }
    }
    /*
    'type'       => ATTACK,
    'id'         => $att['i'],
    'source'     => array(
      'city_id'     => $att['c'],
      'city_name'   => $att['cn'],
      'player_id'   => $att['p'],
      'player_name' => $att['pn'],
      'ally_id'     => $att['a'],
      'ally_name'   => $att['an']),
    'target'     => array(
      'city_id'     => $att['tc'],
      'city_name'   => $att['tcn'],
      'player_id'   => $att['tp'],
      'player_name' => $att['tpn']),
    'state'      => Lou::prepare_attack_state($att['s']),
    'eta'        => $this->get_step_time($att['es']), // estimated time of arival
    'ete'        => $this->get_step_time($att['es'] - time()) // estimated time enroute
    */
  }
}, 'attacks');

$bot->add_tick_event(Cron::TICK1,                           // Cron key
                    "GetAllyAtts",                        // command key
                    "LouBot_alliance_atts_cron",          // callback function
function ($bot, $data) {
  $bot->lou->get_alliance_atts();
}, 'attacks');
?>