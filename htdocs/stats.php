<?php   
  // include main files
  include_once($_SERVER['ENV']['LIB'].'config.php');
  include_once($_SERVER['ENV']['LIB'].'redis.php');
  include_once($_SERVER['ENV']['LIB'].'lou.php');

  // Standard inclusions      
  include_once($_SERVER['ENV']['LIB'].'charts/pChart/pData.class');   
  include_once($_SERVER['ENV']['LIB'].'charts/pChart/pChart.class');   
  include_once($_SERVER['ENV']['LIB'].'charts/pChart/pCache.class');

  // whitch world?
  $worlds = array('w09' => array('name' => 'W9', 'start' => 0),
                  'w10' => array('name' => 'W10', 'start' => 1321952400),
                  'w12' => array('name' => 'W12', 'start' => 1333008000),
                  'w13' => array('name' => 'W13', 'start' => 1337839200),
                  'w14' => array('name' => 'W14', 'start' => 1342771200)
  );

  if (empty($_SERVER['ENV']['WORLD'])) die('no world selected!');
  $world = $worlds[$_SERVER['ENV']['WORLD']]['name'];
  $wstart = $worlds[$_SERVER['ENV']['WORLD']]['start'];
  
  // find definitions
  $use_cache = (isset($_GET['c'])) ? (bool) $_GET['c'] : true;
  define('ALLTIME', -1);
  $advanced = (isset($_GET['advanced'])) ? (bool) $_GET['advanced'] : false;
  $hours = array(24,48,72);
  $stats = array('hours'  => 'Stunden',
                 //'weeks'  => 'Woche',
                 //'months' => 'Monat',
                 //'years'  => 'Jahr'
                );
  $weeks = array(date("n") => 'diese Woche');
  $months = array(date("n") => 'dieser Monat');
  $scale_hours = (in_array($_GET['h'], $hours)) ? $_GET['h'] : 24;
  $selected_stat = (in_array($_GET['stat'], $stats)) ? $_GET['stat'] : 'hours';
  $trans = array(
    'Monday'    => 'Montag',
    'Tuesday'   => 'Dienstag',
    'Wednesday' => 'Mittwoch',
    'Thursday'  => 'Donnerstag',
    'Friday'    => 'Freitag',
    'Saturday'  => 'Samstag',
    'Sunday'    => 'Sonntag',
    'Mon'       => 'Mo',
    'Tue'       => 'Di',
    'Wed'       => 'Mi',
    'Thu'       => 'Do',
    'Fri'       => 'Fr',
    'Sat'       => 'Sa',
    'Sun'       => 'So',
    'January'   => 'Januar',
    'February'  => 'Februar',
    'March'     => 'März',
    'May'       => 'Mai',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'October'   => 'Oktober',
    'December'  => 'Dezember'
  );

  $guid = (empty($_GET['uid'])) ? false : $_GET['uid'];
  $user_id = $redis->HGET('hashes', $guid);

  if ($user_id) {
  $_allstats= array_flip($redis->ZRANGEBYSCORE("user:{$user_id}:stats", "-inf", "+inf", array('withscores' => TRUE)));
  /*
  $_times   = array_keys($_allstats);
  $_tend    = end($_times);
  $_tstart  = reset($_times);
  $_alltimes= array();
  */
  /*
  foreach($_times as $_time) {
    $_tdates[$_time] = getdate($_time);
    if (date('n', $_time) != date('n')) $months[date('n', $_time)] = strtr(strftime('%B', $_time), $trans);
    $_alltimes[$_tdates[$_time]['year']]['stat'] = $_allstats[$_time];
    $_alltimes[$_tdates[$_time]['year']]['month'][$_tdates[$_time]['mon']]['stat'] = $_allstats[$_time];
    $_alltimes[$_tdates[$_time]['year']]['month'][$_tdates[$_time]['mon']]['day'][$_tdates[$_time]['mday']]['stat'] = $_allstats[$_time];
    $_alltimes[$_tdates[$_time]['year']]['month'][$_tdates[$_time]['mon']]['day'][$_tdates[$_time]['mday']]['hours'][$_tdates[$_time]['hours']] = $_allstats[$_time];
    $_alltimes[$_tdates[$_time]['year']]['day'][$_tdates[$_time]['yday']]['stat'] = $_allstats[$_time];
    $_alltimes[$_tdates[$_time]['year']]['day'][$_tdates[$_time]['yday']]['hours'][$_tdates[$_time]['hours']] = $_allstats[$_time];
  }
  #print_r($months);
  */
  if ($scale_hours != ALLTIME) {
    $skip_scale = ($scale_hours != 24) ? 6 : 0;
    $sstart = mktime(date("H")-$scale_hours, 0, 0, date("n"), date("j"), date("Y"));
    $start = ($sstart < $wstart) ? $wstart : $sstart;
    $_dates = array_reverse(range($start, mktime(date("H"), 0, 0, date("n"), date("j"), date("Y")), 60*60), true);
  
    // get user stats
    $latest = '';
    $_user_stats = array();
    reset($_dates);
    $_i = 2;
    foreach($_dates as $_start) {
      $_end = $_start + (60*60);
      $_stats = array_flip($redis->ZRANGEBYSCORE("user:{$user_id}:stats", "({$_start}", "({$_end}", array('withscores' => TRUE, 'limit' => array(0, 1))));
      if (empty($_stats) && !empty($latest)){
        $_user_stats[$_start] = $latest . '|' . $_i;
        $_i++;
      } else if (empty($_stats)){
        $_user_stats[$_start] = $latest;
      } else {
        $_user_stats[key($_stats)] = end($_stats);
        $latest = end($_stats);
        $_i = 2;
      }
    }
  } else {
    /*
    $_stats   = array_flip($redis->ZRANGEBYSCORE("user:{$user_id}:stats", "-inf", "+inf", array('withscores' => TRUE)));
    $_times   = array_keys($_stats);
    $_tend    = end($_times);
    $_tstart  = reset($_times);
    $_alltimes= array();
    echo date('d.m H:i', $_tstart).'<br/>';
    echo date('d.m H:i', $_tend).'<br/>';
    foreach($_times as $_time) {
      $_tdates[$_time] = getdate($_time);
      $_alltimes[$_tdates[$_time]['year']]['stat'] = $_stats[$_time];
      $_alltimes[$_tdates[$_time]['year']]['month'][$_tdates[$_time]['mon']]['stat'] = $_stats[$_time];
      $_alltimes[$_tdates[$_time]['year']]['month'][$_tdates[$_time]['mon']]['day'][$_tdates[$_time]['mday']]['stat'] = $_stats[$_time];
      $_alltimes[$_tdates[$_time]['year']]['month'][$_tdates[$_time]['mon']]['day'][$_tdates[$_time]['mday']]['hours'][$_tdates[$_time]['hours']] = $_stats[$_time];
      $_alltimes[$_tdates[$_time]['year']]['day'][$_tdates[$_time]['yday']]['stat'] = $_stats[$_time];
      $_alltimes[$_tdates[$_time]['year']]['day'][$_tdates[$_time]['yday']]['hours'][$_tdates[$_time]['hours']] = $_stats[$_time];
    }
    echo "<pre>";print_r($_alltimes);exit;
    */
  }
  
  $user = $redis->HGETALL("user:{$user_id}:data");
  $alliance = $redis->HGETALL("alliance:{$user['alliance']}:data");
  if ($advanced) $cities = $redis->SMEMBERS("user:{$user_id}:cities");
  if (is_array($cities)) foreach ($cities as $cid => $pos) {
    $city_id = $redis->HGET("cities", $pos);
    // get city stats
    $latest = array();
    $_city_stats = array();
    reset($_dates);
    foreach($_dates as $_start) {
      $_end = $_start + (60*60);
      $_stats = array_flip($redis->ZRANGEBYSCORE("city:{$city_id}:stats", "({$_start}", "({$_end}", array('withscores' => TRUE, 'limit' => array(0, 1))));
      if (empty($_stats)){
        $_city_stats[$_start] = $latest;
      } else {
        $_city_stats[key($_stats)] = end($_stats);
        $latest = end($_stats);
      }
    }
    $latest = array();
    $city_stats = array_reverse($_city_stats, true);
    foreach($city_stats as $k => $v) {
      if (empty($city_stats[$k]) && !empty($latest)) $city_stats[$k] = $latest;
      $latest = $city_stats[$k];
    }
    $cities[$cid] = array('data' => $redis->HGETALL("city:{$city_id}:data"), 'stats' => $city_stats);
    // mini graph
    // Dataset definition
    $mpoints  = array();
    $mtype    = array();
    $mname    = array();
    $mally    = array();
    $mowner   = array();
    $mtime    = array();
    $mlast_owner = 0;
    $mlast_ally = 0;
    $mlast_name = '';
    $mlast_type  = 0;
    $mdata_count = 0;
    array_walk($cities[$cid]['stats'], function(&$val, $key) {
      global $skip_scale, $mlast_ally, $mally, $mlast_name, $mname, $mlast_owner, $mowner, $mpoints, $mtype, $mlast_type, $mtime, $mdata_count, $trans, $redis;
      //name|state|water|alliance_id|user_id|points
      $_time = getdate($key);
      if (($_time['hours'] == 0 || $_time['hours'] == 12) OR ($skip_scale > 0)) {
        $abscise_key = $mtime[] = date('d.m H:i', $key);
      } else {
        $abscise_key = $mtime[] = strtr(strftime('%a %H:%M', $key), $trans);
      }
      $val = explode('|', $val);
      if ($val[0] != $mlast_name && $mdata_count > 0) $mname[$abscise_key] = array($val[0], $mlast_name);
      $mlast_name = $val[0];
      if ($val[1] != $mlast_type && $mdata_count > 0) $mtype[$abscise_key] = array(array('s' => $val[1], 'w' => $val[2]), array('s' => $mlast_type, 'w' => $val[2]));
      $mlast_type = $val[1];
      if ($val[3] != $mlast_ally && $mdata_count > 0) $mally[$abscise_key] = array($val[3] , $mlast_ally);
      $mlast_ally = $val[3];
      if ($val[4] != $mlast_owner && $mdata_count > 0) $mowner[$abscise_key] = array($val[4], $mlast_owner);
      $mlast_owner = $val[4];
      $mpoints[] = $val[5];
      $mdata_count++;
     }
    );
    $mperformance = number_format(round((($mpoints[count($mpoints)-1]/$mpoints[0]) - 1) * 100, 2), 2, ',', '');  
    // mFile
    $mFileName = './tmp/'.md5($city_id).".png";
    // mgraph
    $mDataSet = new pData;  
    $mDataSet->AddPoint($mpoints,"points");  
    $mDataSet->AddAllSeries();  
    $mDataSet->SetAbsciseLabelSerie();  
    $mDataSet->SetSerieName("Punkte","points");
    // Cache definition 
    $mCache = new pCache('../charts/Cache/');
    if (($use_cache && !$mCache->GetFromCache(md5($city_id) ,$mDataSet->GetData(), $mFileName)) OR !$use_cache) {
      // Initialise the graph  
      $mTest = new pChart(100,30);  
      $mTest->setFontProperties("Fonts/tahoma.ttf",8);  
      #$mTest->drawFilledRoundedRectangle(2,2,98,28,2,255,255,255);  
      #$mTest->drawRoundedRectangle(2,2,98,28,2,163,203,167);
      $mTest->setGraphArea(5,5,95,25);
      $mTest->drawGraphArea(255,255,255);
      $mTest->drawScale($mDataSet->GetData(),$mDataSet->GetDataDescription(),SCALE_DIFF,255,255,255,FALSE,0,2,FALSE,TRUE,FALSE);     
      // Draw the line graph  
      $mTest->drawLineGraph($mDataSet->GetData(),$mDataSet->GetDataDescription());  
      // Finish the graph
      $mCache->WriteToCache(md5($city_id),$mDataSet->GetData(),$mTest);
      $mTest->Render($mFileName);
    }
    $cities[$cid]['mgraph'] = $mFileName;
    // Dataset definition
    // cFile
    $cFileName = './tmp/'.md5($cities[$cid]['data']['pos']).".png";
    // cgraph
    $cDataSet = new pData;
    $cDataSet->AddPoint($mpoints,"points");
    $cDataSet->AddPoint($mtime,"time");
    $cDataSet->AddSerie("points");
    $cDataSet->SetAbsciseLabelSerie();
    $cDataSet->SetSerieName("Punkte","points");
    $cDataSet->SetYAxisName("Punkte");  
    $cDataSet->SetAbsciseLabelSerie("time");
    //$cDataSet->SetXAxisFormat("date");
    // Cache definition 
    $cCache = new pCache('../charts/Cache/');
    if (($use_cache && !$cCache->GetFromCache(md5($cities[$cid]['data']['pos']) ,$cDataSet->GetData(), $cFileName)) OR !$use_cache) {
      // Initialise the graph
      $cTest = new pChart(615,230);
      $cTest->setDateFormat('d.m H:i');
      $cTest->setFontProperties("../charts/Fonts/tahoma.ttf",8);
      $cTest->setGraphArea(60,30,550,150);
      $cTest->drawFilledRoundedRectangle(7,7,608,223,5,240,240,240);
      $cTest->drawRoundedRectangle(5,5,610,225,5,163,203,167);
      $cTest->drawGraphArea(255,255,255,TRUE);
      $cTest->drawGraphAreaGradient(163,203,167,50);
      $cTest->drawScale($cDataSet->GetData(),$cDataSet->GetDataDescription(),SCALE_DIFF,150,150,150,TRUE,75,0,FALSE,$skip_scale);
      $cTest->drawGrid(4,TRUE,230,230,230,40);
      // Draw the graph
      $cTest->drawFilledCubicCurve($cDataSet->GetData(),$cDataSet->GetDataDescription(),.1, 30);
      if ($scale_hours <= 48) $cTest->drawPlotGraph($cDataSet->GetData(),$cDataSet->GetDataDescription(),2,1,255,255,255);
      // Draw labels
      if (!empty($mname)) foreach($mname as $k => $v) {
        $cTest->setLabel($cDataSet->GetData(),$cDataSet->GetDataDescription(),"points",$k,$v[1],239,233,195);
      }
      if (!empty($mtype)) foreach($mtype as $k => $v) {
        $cTest->setLabel($cDataSet->GetData(),$cDataSet->GetDataDescription(),"points",$k,'Status: '.LoU::prepare_city_type($v[0]),221,230,174);
      }
      if (!empty($mowner)) foreach($mowner as $k => $v) {
        $_un = $redis->HGET("user:{$v[1]}:data", 'name');
        if ($_un) $cTest->setLabel($cDataSet->GetData(),$cDataSet->GetDataDescription(),"points",$k,'Übernahme: '.$_un,239,233,195);
      }
      $cTest->clearShadow(); 

      // Finish the graph
      $cTest->drawLegend(75,35,$cDataSet->GetDataDescription(),236,238,240,52,58,82);
      $cTest->setFontProperties("../charts/Fonts/tahoma.ttf",10);
      $cTest->drawTitle(60,22,$cities[$cid]['data']['name'] . ' - ' . $scale_hours.'h'. ' Performance: ' .$mperformance.'%',50,50,50,585);

      // Render the graph
      $cCache->WriteToCache(md5($cities[$cid]['data']['pos']),$cDataSet->GetData(),$cTest);
      $cTest->Render($cFileName);
    }
    $cities[$cid]['cgraph'] = $cFileName;
  }
  $_cities[0] = array();
  $_cities[1] = array();
  $_cities[2] = array();
  $city_order = 'name';
  if (!empty($cities)) foreach($cities as $city) {
    $_cities[$city['data']['state']][$city['data'][$city_order]] = $city;
  }
  sort($_cities[0]); sort($_cities[1]); sort($_cities[2]);
  $points = array();
  $ccities = array();
  $rank   = array();
  $ally   = array();
  $time   = array();
  $last_ally = 0;
  $city_count = 0;
  $data_count = 0;
  
  $_i = 2;
  $latest = '';
  $user_stats = array_reverse($_user_stats, true);

  foreach($user_stats as $k => $v) {
    if (empty($user_stats[$k]) && !empty($latest)) {
      $user_stats[$k] = $latest . '|' . $_i;
      $_i++;
    } else $_i = 2;
    $latest = $user_stats[$k];
  }
  $last_points = 0;
  $last_rank = 0;
  
  array_walk($user_stats, function(&$val, $key) {
    global $skip_scale, $city_count, $last_ally, $ally, $ccities, $points, $rank, $time, $data_count, $trans, $last_points, $last_rank;
    //alliance_id|city_count|points|rank|periode
    $_time = getdate($key);
    if (($_time['hours'] == 0 || $_time['hours'] == 12) OR ($skip_scale > 0)) {
      $abscise_key = $time[] = date('d.m H:i', $key);
    } else {
      $abscise_key = $time[] = strtr(strftime('%a %H:%M', $key), $trans);
    }
    $val = explode('|', $val);
    if ($val[0] != $last_ally && $data_count > 0) $ally[$abscise_key] = $val[0];
    $last_ally = $val[0];
    if ($val[1] != $city_count && $data_count > 0) $ccities[$abscise_key] = $val[1];
    $city_count = $val[1];
    if (!$val[4]) { 
      $last_points = $points[] = $val[2];
      $last_rank = $rank[] = $val[3];
    } else {
      $_points = $val[2] - $last_points;
      $_rank = $val[3] - $last_rank;
      $last_points = $points[] = $last_points + round( $_points / $val[4] );
      $last_rank = $rank[] = $last_rank + round( $_rank / $val[4] );
    }
    $data_count++;
  }
  );
  $performance = number_format(round((($points[count($points)-1]/$points[0]) - 1) * 100,2), 2, ',', '');  
  // Dataset definition
  // File
  $FileName = './tmp/'.md5($user['name']).".png";
  // Graph
  $DataSet = new pData;
  $DataSet->AddPoint($points,"points");
  $DataSet->AddPoint($rank,"rank");
  $DataSet->AddPoint($time,"time");
  $DataSet->AddSerie("points");

  $DataSet->SetAbsciseLabelSerie();
  $DataSet->SetSerieName("Punkte","points");
  $DataSet->SetSerieName("Rank","rank");
  $DataSet->SetYAxisName("Punkte");  
  $DataSet->SetAbsciseLabelSerie("time");
  //$DataSet->SetXAxisFormat("date");
  // Cache definition 
  $Cache = new pCache('../charts/Cache/');
  if (($use_cache && !$Cache->GetFromCache(md5($user['name']) ,$DataSet->GetData(), $FileName)) OR !$use_cache) {
    // Initialise the graph
    $Test = new pChart(715,230);
    $Test->setDateFormat('d.m H:i');
    $Test->setFontProperties("../charts/Fonts/tahoma.ttf",8);
    $Test->setGraphArea(60,30,650,150);
    $Test->drawFilledRoundedRectangle(7,7,708,223,5,240,240,240);
    $Test->drawRoundedRectangle(5,5,710,225,5,163,203,167);
    $Test->drawGraphArea(255,255,255,TRUE);
    $Test->drawGraphAreaGradient(163,203,167,50);
    $Test->drawScale($DataSet->GetData(),$DataSet->GetDataDescription(),SCALE_DIFF,150,150,150,TRUE,75,0,FALSE,$skip_scale);
    $Test->drawGrid(4,TRUE,230,230,230,40);
    // Draw the graph
    $Test->drawFilledCubicCurve($DataSet->GetData(),$DataSet->GetDataDescription(),.1, 30);
    if ($scale_hours <= 48) $Test->drawPlotGraph($DataSet->GetData(),$DataSet->GetDataDescription(),2,1,255,255,255);
    // Draw labels
    if (!empty($ccities)) foreach($ccities as $k => $v) {
      $Test->setLabel($DataSet->GetData(),$DataSet->GetDataDescription(),"points",$k,"Städte: {$v}",239,233,195);
    }
    $Test->clearShadow();
    // Clear the scale  
    $Test->clearScale();  

    // Draw the 2nd graph   
    $DataSet->RemoveSerie("points");  
    $DataSet->AddSerie("rank");  
    $DataSet->SetYAxisName("Rank"); 
    $Test->drawRightScale($DataSet->GetData(),$DataSet->GetDataDescription(),SCALE_DIFF,150,150,150,TRUE,75,0,FALSE,$skip_scale,TRUE); 
    // Draw the 0 line
    $Test->setFontProperties("../charts/Fonts/tahoma.ttf",6);
    $Test->drawTreshold(0,143,55,72,TRUE,TRUE);
    // Draw the Line graph
    $Test->drawFilledCubicCurve($DataSet->GetData(),$DataSet->GetDataDescription(), .1, 20);
    if ($scale_hours <= 48) $Test->drawPlotGraph($DataSet->GetData(),$DataSet->GetDataDescription(),2,1,255,255,255);
    // Draw Labels
    $Test->setFontProperties("../charts/Fonts/tahoma.ttf",8);
    if (!empty($ally)) foreach($ally as $k => $v) {
    if ($v != 0) {
      $_alliance = $redis->HGETALL("alliance:{$v}:data");
      $Test->setLabel($DataSet->GetData(),$DataSet->GetDataDescription(),"rank",$k,"Ally: {$_alliance['name']}",221,230,174);
    } else $Test->setLabel($DataSet->GetData(),$DataSet->GetDataDescription(),"rank",$k,"keine Alliance",221,230,174);
    }
    // Finish the graph
    $Test->drawLegend(75,35,$DataSet->GetDataDescription(),236,238,240,52,58,82);
    $Test->setFontProperties("../charts/Fonts/tahoma.ttf",10);
    $Test->drawTitle(60,22,$user['name']. ((!empty($alliance['name'])) ? ' [' . $alliance['name'] . ']': '') . ' - '.$scale_hours.'h'. ' Performance: ' .$performance.'%',50,50,50,585);

    // Render the graph
    $Cache->WriteToCache(md5($user['name']),$DataSet->GetData(),$Test);
    $Test->Render($FileName);
  } 
}
$reload_uri = str_replace('&c=0', '', $_SERVER['REQUEST_URI']);
header("refresh:600;url={$reload_uri}");
header("Expires: 0"); 
header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
header("Cache-Control: private", false);
header("Content-type: text/html; charset=utf-8"); 
?>
<html>
 <head>
  <title><?php echo $world;?> - Stats</title>
  <link type="text/css" href="script/custom-theme/jquery-ui-1.8.16.custom.css" rel="stylesheet" />
  <link type="text/css" href="script/chosen/chosen.css" rel="stylesheet" />	
  <script type="text/javascript" src="script/jquery-1.6.2.min.js"></script>
  <script type="text/javascript" src="script/jquery-ui-1.8.16.custom.min.js"></script>
  <script type="text/javascript" src="script/jquery.cookie.js"></script>
  <script type="text/javascript" src="script/chosen/chosen.jquery.js"></script>
  <script type="text/javascript">
    $(function() {
      // Submit-Button
      $("#submitBtn").button();
      // clear Cache
      $("#clearcacheBtn").button().click(function() {
        $('#target').submit();
      });
      // advance
      $("#advanceBtn").button().click(function() {
        //$('#tabs').toggle($(this).checked);
        $('#target').submit();
      });
      // Radios
      $("#radioset_hours").buttonset();
      $("#radioset_hours").find('input:radio').click(function() {
        $('#target').submit();
      });
      // Tabs
      $('#tabs').tabs({collapsible: true, cookie: { }});
      // Accordions
      $('#tabs div.accordions').each(function(index) {
        var cookieName = 'ui-accordion-'+ index;
        var activeHeader = parseInt($.cookie(cookieName) || 0);
        $(this).accordion(
         {
          autoHeight: false,
          clearStyle: true,
          header: "h3",
          collapsible: true,
          active: activeHeader,
          change: function(e, ui)
          {
            $.cookie(cookieName, $(this).find("h3").index(ui.newHeader[0]));
          }
         }
        );
        // Choosen
        $("#statSelect").chosen({disable_search_threshold:999}).change(function(){
          //console.log($(this).val());
        });
      });
      // Autocomplete
      /*
      var cache = {}, lastXhr;
      $( "#autoName" ).autocomplete({
        minLength: 2,
        source: function( request, response ) {
          var term = request.term;
          if ( term in cache ) {
            response( cache[ term ] );
            return;
          }
          lastXhr = $.getJSON( "autocomplete.php", request, function( data, status, xhr ) {
            cache[ term ] = data;
            if ( xhr === lastXhr ) {
              response( data );
            }
          });
        }
      });
     */
    });
  </script>
  <style type="text/css">
    body{ font: 62.5% "Trebuchet MS", sans-serif;}
    .ui-autocomplete-loading { background: white url('script/custom-theme/images/ui-anim_basic_16x16.gif') right center no-repeat; }
    .ui-accordion .ui-accordion-header {
      background: none;
    }
    .ui-autocomplete {
      max-height: 100px;
      overflow-y: auto;
      /* prevent horizontal scrollbar */
      overflow-x: hidden;
      /* add padding to account for vertical scrollbar */
      padding-right: 20px;
    }
    .chzn-container {
      /*margin-left: 4px;*/
    }
  </style>
 </head>
 <body>
  <?php if (isset($FileName)) {?>
  <form style="margin-top: 1em;" method="get" name="form" id="target">
   <div id="radioset_hours" class="ui-buttonset" style="margin-left: 5px; margin-top: 2px; float: left;">
    <?php if (is_array($hours)) foreach($hours as $key => $hour) { 
      $checked = ($hour == $scale_hours) ? ' checked="checked"' : '';
      echo "<input type=\"radio\" id=\"hours_{$key}\" value=\"{$hour}\" name=\"h\"{$checked}/><label for=\"hours_{$key}\">{$hour}h</label>";
    } ?>
   </div>
   <div id="radioset_weeks" class="ui-buttonset" style="margin-left: 5px; margin-top: 2px; float: left;display:none;">
    <?php if (is_array($week))  foreach($week as $key => $day) { 
      $checked = ($day == $scale_week) ? ' checked="checked"' : '';
      echo "<input type=\"radio\" id=\"radio{$key}\" value=\"{$day}\" name=\"h\"{$checked}/><label for=\"radio{$key}\">{$day}h</label>";
    } ?>
   </div>
   <div id="radioset_months" class="ui-buttonset" style="margin-left: 5px; margin-top: 2px; float: left;display:none;">
    <?php reset($months);if (is_array($months)) foreach($months as $key => $month) {
      $checked = ($month == $scale_months) ? ' checked="checked"' : '';
      echo "<input type=\"radio\" id=\"month_{$key}\" value=\"{$key}\" name=\"m\"{$checked}/><label for=\"month_{$key}\">{$month}</label>";
    } ?>
   </div>
   <div id="radioset_years" class="ui-buttonset" style="margin-left: 5px; margin-top: 2px; float: left;display:none;">
    <?php if (is_array($year)) foreach($year as $key => $year) { 
      $checked = ($year == $scale_years) ? ' checked="checked"' : '';
      echo "<input type=\"radio\" id=\"radio{$key}\" value=\"{$year}\" name=\"h\"{$checked}/><label for=\"radio{$key}\">{$year}h</label>";
    } ?>
   </div>
   <!--div style="margin-left: 5px; margin-top: 2px; float: left;">
    <select data-no_search="true" class="chzn-select" id="statSelect" name="stat">
      <?php foreach($stats as $_key => $_stat) { 
        $checked = ($_key == $selected_stat) ? ' selected="selected"' : '';
        echo "<option value=\"{$_key}\"{$checked}/>{$_stat}</option>";
      } ?>
    </select>
   </div-->
   <!--div id="autocompleter" style="margin-left: 5px; margin-top: 2px;float: left;">
		<input id="autoName" name="name" style="z-index: 100; position: relative;" title="type &quot;name&quot;" value="<?=$user_name?>"/>
   </div-->
   <div style="margin-left: 5px; float: left;">
    <button type="submit" id="submitBtn">Anzeigen</button>
   </div>
   <div style="margin-left: 5px; margin-top: 2px; float: left;">
    <input type="checkbox" name="advanced" id="advanceBtn" value="true"<?=($advanced) ? ' checked="checked"' : ''?>/><label for="advanceBtn">Erweitert</label>
   </div>
   <div style="margin-left: 5px; margin-top: 2px; float: left;">
    <input type="checkbox" name="c" id="clearcacheBtn" value="0"/><label for="clearcacheBtn">Cache l&ouml;schen</label>
   </div>
   <!--div style="margin-left: 5px; margin-top: 2px;">
    <select data-placeholder="alle Kontinente" style="width:250px;" multiple class="chzn-select" id="continentSelect" name="continents">
      <option value=""></option>
      <option>American Black Bear</option>
      <option>Asiatic Black Bear</option>
      <option>Brown Bear</option>
      <option>Giant Panda</option>
      <option selected>Sloth Bear</option>
      <option disabled>Sun Bear</option>
      <option selected>Polar Bear</option>
      <option disabled>Spectacled Bear</option>
    </select>
   </div-->
   <div style="clear:left;">
    <img src='<?=$FileName?>' alt='<?=$FileName?>' border='0'/>
   </div>
   <div id="tabs" style="margin-left: 5px; margin-top: 2px; width: 701px;<?=($advanced) ? '' : ' display: none;'?>">
    <ul>
      <li><a href="#tabs-cities">St&auml;dte</a></li>
      <li><a href="#tabs-castles">Burgen</a></li>
      <li><a href="#tabs-palasts">Palaste</a></li>
    </ul>
    <div id="tabs-cities">
      <div id="accordion-cities" class="accordions">
        <?php if (!empty($_cities[0])) foreach($_cities[0] as $c) { ?>
        <div>
          <h3><a href="#" style="background: url(<?=$c['mgraph']?>) 0% 50% no-repeat;"><?=$c['data']['name']?></a></h3>
          <div>Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet.<br/><img src='<?=$c['cgraph']?>' alt='<?=$c['cgraph']?>' border='0'/></div>
        </div>
        <?php } else { ?>
          <p>keine St&auml;dte</p>
        <?php } ?>
      </div>
    </div>
    <div id="tabs-castles">
      <div id="accordion-castles" class="accordions">
        <?php if (!empty($_cities[1])) foreach($_cities[1] as $c) { ?>
        <div>
          <h3><a href="#" style="background: url(<?=$c['mgraph']?>) 0% 50% no-repeat;"><?=$c['data']['name']?></a></h3>
          <div>Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet.<br/><img src='<?=$c['cgraph']?>' alt='<?=$c['cgraph']?>' border='0'/></div>
        </div>
        <?php } else { ?>
          <p>keine Burgen</p>
        <?php } ?>
      </div>
    </div>
    <div id="tabs-palasts">
      <div id="accordion-palasts" class="accordions">
        <?php if (!empty($_cities[2])) foreach($_cities[2] as $c) { ?>
        <div>
          <h3><a href="#" style="background: url(<?=$c['mgraph']?>) 0% 50% no-repeat;"><?=$c['data']['name']?></a></h3>
          <div>Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet.<br/><img src='<?=$c['cgraph']?>' alt='<?=$c['cgraph']?>' border='0'/></div>
        </div>
        <?php } else { ?>
          <p>keine Palaste</p>
        <?php } ?>
      </div>
    </div>
   </div>
   <input id="uid" name="uid" type="hidden" value="<?=$guid?>"/>
  </form>
  <?php } else { ?>
  <p>Spieler nicht bekannt!</p>
  <?php } ?>
  <div class="ui-widget" style="margin-left: 5px; width: 707px;">
		<div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"> 
		 <p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span>
		 <strong>Hey!</strong> Wer hilft bei nem netten Design?</p>
		</div>
	</div>
  <div class="ui-widget" style="margin-left: 5px; width: 707px;">
		<div class="ui-state-error ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"> 
		 <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
		  <script type='text/javascript'><!--// <![CDATA[
        var OA_source = '56356';
        // ]]> -->
      </script>
      <script type='text/javascript' src='http://ads.simplyroot.de/www/delivery/spcjs.php?id=5'></script>
      <script type='text/javascript'><!--// <![CDATA[
        OA_show(7);
        // ]]> -->
      </script>
      <noscript><a target='_blank' href='http://ads.simplyroot.de/www/delivery/ck.php?n=a8b4e575'><img border='0' alt='' src='http://ads.simplyroot.de/www/delivery/avw.php?zoneid=7&n=a8b4e575' /></a></noscript>
		</div>
	</div>
 </body>
<html>
