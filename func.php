<?php

function linkCallsign( $callsign ) {
  $call = $callsign;
  $tmp = explode( "-", $callsign );
  $call = $tmp[0];
  $suffix = $tmp[1];
  if( !empty( $suffix )) {
    $suffix="-$suffix";
  }
  if( !is_numeric( $call )) {
    $call = "<a href=\"https://qrz.com/db/$call\" target=\"_blank\">$call" . "</a>$suffix";
  } elseif( strlen( $call ) == 7 ) {
    $call = "<a href=\"https://ham-digital.org/dmr-userreg.php?usrid=$call\"" . " target=\"_blank\">$call</a>";
  }

  return $call;
}

function rssiCalc( $val ) {
  if( $val > -53 ) $rssi = "S9+40dB";
  else if( $val > -63 ) $rssi = "S9+30dB";
  else if( $val > -73 ) $rssi = "S9+20dB";
  else if( $val > -83 ) $rssi = "S9+10dB";
  else if( $val > -93 ) $rssi = "S9";
  else if( $val > -99 ) $rssi = "S8";
  else if( $val > -105 ) $rssi = "S7";
  else if( $val > -111 ) $rssi = "S6";
  else if( $val > -117 ) $rssi = "S5";
  else if( $val > -123 ) $rssi = "S4";
  else if( $val > -129 ) $rssi = "S3";
  else if( $val > -135 ) $rssi = "S2";
  else if( $val > -141 ) $rssi = "S1";
  return "$rssi ($val dBm)";
}

function printTable( $time, $callsign, $dgid, $duration, $repeater, $loss = "0", $ber = "0" ) {
  echo "  <tr>\n" .
    "<td>$time</td>\n" .
    "<td>" . linkCallsign( $callsign ) ."</td>\n" .
    "<td>$dgid</td>\n" .
    "<td>$duration</td>\n" .
    "<td>$repeater</td>\n" .
    "<td>$loss</td>\n" .
    "<td>$ber</td>\n" .
  "</tr>\n";
}

function getLastHeard($limit = MAXENTRIES) {
  $logPath = LOGPATH."/".MMDVM_PREFIX."-*.log";
  //$logLines =  explode( "\n", `egrep -h "network (data|watchdog)|RF end" $logPath | tail -$limit` );
  //$logLines =  explode( "\n", `egrep -h "YSF" $logPath | tail -$limit` );
  $logLines =  explode( "\n", `egrep -h "YSF" $logPath` );

  $oldline = "";

  $time     = "";
  $loss     = "";
  $ber      = "";
  $rssi     = "";
  $call     = "";
  $duration = "";
  $repeater = "";

  //$key = 0;

  $printLines = [];
  //$old_time = "";
  $new_time = "";

  foreach( $logLines as $line ) {
    $time = date( "Y-m-d H:i:s", strtotime( substr( $line, 3, 23 )." UTC" ));
    
    if( strpos( $line, "network data" )) {
      //if( empty( $old_time )) {
      if( !strpos( $oldline, "network data" )) {
        $old_time = strtotime( substr( $line, 3, 19 ));
      }
        //$old_time = substr( $line, 3, 23 );
      //}
      $oldline = $line;
    } else {
      if( strpos( $line, "network watchdog" )) {
        $callsign = substr( $oldline, 59, strpos( $oldline, "to" ) - 59 );
        $dgid = substr( $oldline, 79, strpos( $oldline, "at " ) - 79 );
        //$duration = substr( $line, 62, strpos( $line, "seconds,", 62 ) - 62 ) . "sec";
        //$duration = substr( $line, 62, strpos( $line, "seconds,", 62 ) - 62 );
        $new_time = strtotime( substr( $oldline, 3, 19 ));
        //$new_time = substr( $line, 3, 19 );
        //$duration = floatval( $new_time - $old_time );
        $duration = round( floatval(( $new_time - $old_time )), 1);
        //$duration = date("i:s", floatval( $new_time - $old_time ) / 100000 );
        //$old_time = "";
        echo "<pre><code>$time\nold_time: $old_time\nnew_time: $new_time\n$callsign</code></pre>";
        $repeater = substr( $oldline, strpos( $oldline, "at " ) + 3, strpos( $oldline, " ", strpos( $oldline, "at " ) + 3) - strpos( $oldline, "at " ) + 3 );
        $loss = substr( $line, 75, strpos( $line, "%", 75 ) - 74 );
        $ber = substr( $line, 96, strpos( $line, "%", 96 ) - 95 );
      } elseif( strpos( $line, "RF end of" )) {
        $oldline = "";
        $callsign = substr( $line, 69, strpos( $line, "to" ) - 69 );
        $dgid = substr( $line, 89, strpos( $line, ",", 89 ) - 89 );
        $duration = trim( substr( $line, 92, strpos( $line, "seconds,", 92 ) - 92 ), " ,");
        $rssi_values = explode( "/", substr( $line, 113, strpos( $line, "dBm", 113 ) - 113 ));
        $rssi = rssiCalc( round( array_sum( $rssi_values ) / count( $rssi_values )));
        $loss = "---";
        $ber = substr( $line, 111, strpos( $line, ",", 111 ) - 111 );
        if( empty( $ber )) $ber = "---";
        $repeater = $rssi; // use this testwise, debug
      } else {
        $oldline = "";
        continue;
      }
      $tmp = [];
      $tmp['time'] = $time;
      $tmp['callsign'] = $callsign;
      $tmp['dgid'] = $dgid;
      $tmp['duration'] = $duration;
      $tmp['repeater'] = $repeater;
      $tmp['loss'] = $loss;
      $tmp['ber'] = $ber;
      array_unshift( $printLines, $tmp );
      unset( $tmp );
    } // end if clauses
  } // end foreach

  $c = 0;
  
  foreach( $printLines as $key=>$line ) {
    printTable(
      $line['time'],
      $line['callsign'],
      $line['dgid'],
      $line['duration'],
      $line['repeater'],
      $line['loss'],
      $line['ber']
    );
    if( ++$c >= MAXENTRIES ) break;
  } // end foreach $printLines
} // end function

function printLogs($limit = MAXLOGENTRIES) {
  $logPath  = LOGPATH."/*-".gmdate("Y-m-d").".log";
  $logLines = explode("\n", `tail -n $limit $logPath`);

  echo "\n<!-- start logfile output -->\n<h2>DEBUG LOGFILES OUTPUT</h2>\n";
  echo "<div style=\"text-align:left;font-size:0.8em;\"><code><pre>\n";

  foreach( $logLines as $line ) {
    if ( substr( $line, 0, 4) == "==> " ) {
      echo "<strong style=\"font-size:1.3em;\">$line</strong>\n";
    } else {
      echo "$line\n";
    }
  }

  echo "\n</pre></code></div>\n<!-- end logfile output -->\n\n";
  return 0;
}

