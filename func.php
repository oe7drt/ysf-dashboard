<?php

/* 
 * func.php
 * Dashboard for YSFGateway
 * Manually compiled and configured MMDVMHost with YSFGateway
 * master branch (no DG-ID)
 * connecting to YCS232 with multiple DG-ID support
 *
 */

function getUptime() {
  $uptime = intval( `cat /proc/uptime | awk '{ print $1 }'` );
  if( $uptime >= 60 ) {
    // in minutes
    $minutes = intval( $uptime / 60 );
    $seconds = $uptime % 60;
    if( $minutes >= 60 ) {
      $hours = intval( $minutes / 60 );
      $minutes = $minutes % 60;
      if( $hours >= 24 ) {
        $days = intval( $hours / 24 );
        $hours = $hours % 24;
        $out = "$days days $hours hours $minutes minutes and $seconds seconds";
      } else {
        // no days, only hours minutes and seconds
        $out = "$hours hours $minutes minutes and $seconds seconds";
      }
    } else {
      // mintes < 60 only minuts, hours
      $out = "$minutes minutes and $seconds seconds";
    }
  } else {
    // only seconds
    $out = "$uptime seconds";
  }

  return $out;
}

function linkCallsign( $callsign ) {
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

function printTable( $time, $callsign, $dgid, $duration, $repeater, $loss = "---", $ber = "---" ) {
  if( $duration >= 60 ) {
    $min = str_pad( intval( $duration / 60 ), 2, "0", STR_PAD_LEFT );
    $sec = str_pad( $duration % 60, 2, "0", STR_PAD_LEFT );
    $duration = "$min:$sec";
  } else {
    $duration = "00:" . str_pad( $duration, 2, "0", STR_PAD_LEFT );
  }
  echo "  <tr>\n" .
    "<td>$time</td>\n" .
    "<td>" . linkCallsign( $callsign ) ."</td>\n" .
    "<td>$dgid</td>\n" .
    "<td>$repeater</td>\n" .
    "<td>$duration</td>\n" .
    "<td>$loss</td>\n" .
    "<td>$ber</td>\n" .
  "</tr>\n";
}

function getLastHeard($limit = MAXENTRIES) {
  $logPath = LOGPATH."/".MMDVM_PREFIX."-*.log";
  //$logLines =  explode( "\n", `egrep -h "network (data|watchdog)|RF end of transmission" $logPath | tail -$limit` );
  //$logLines =  explode( "\n", `egrep -h "YSF" $logPath | tail -$limit` );
  $logLines =  explode( "\n", `egrep -h "YSF," $logPath` );

  $oldline = "";

  $time     = "";
  $loss     = "";
  $ber      = "";
  $rssi     = "";
  $call     = "";
  $duration = "";
  $repeater = "";

  $printLines = [];

  foreach( $logLines as $line ) {
  	if( empty( $oldline ) && strpos( $line, "network watchdog" )) {
      // $oldine=$line;
      continue;
    }

  	if( strpos( $line, "RF end of transmission" )) {
        $time = date( "Y-m-d H:i:s", strtotime( substr( $line, 3, 23 )." UTC" ));
        $callsign = substr( $line, 69, strpos( $line, "to" ) - 69 );
        $dgid = substr( $line, 89, strpos( $line, ",", 89 ) - 89 );
        $duration = round( trim( substr( $line, 92, strpos( $line, "seconds,", 92 ) - 92 ), " ," ));
        $rssi_values = explode( "/", substr( $line, 113, strpos( $line, "dBm", 113 ) - 113 ));
        $rssi = rssiCalc( round( array_sum( $rssi_values ) / count( $rssi_values )));
        $loss = "---";
        $ber = substr( $line, 111, strpos( $line, ",", 111 ) - 111 );
        if( empty( $ber )) $ber = "---";
        $repeater = $rssi; // use this testwise, debug
  	} elseif( strpos( $line, "network data" )) {
  		if( strpos( $oldline, "network data" )) {
        $oldline = $line;
  			continue;
  		} else {
        $time = date( "Y-m-d H:i:s", strtotime( substr( $line, 3, 23 )." UTC" ));
  			$old_time = strtotime( $time );
        $oldline=$line;
        continue;
  		}
  	} elseif( strpos( $line, "network watchdog" )) {
      $time = date( "Y-m-d H:i:s", strtotime( substr( $oldline, 3, 23 )." UTC" ));
		  $callsign = substr( $oldline, 59, strpos( $oldline, "to" ) - 59 );
		  $dgid = substr( $oldline, 79, strpos( $oldline, "at " ) - 79 );
		  $new_time = strtotime( date( "Y-m-d H:i:s", strtotime( substr( $oldline, 3, 23 )." UTC" )));
		  // echo "<pre><code>\$callsign: $callsign at \$dgid: $dgid\n\$old_time: ".date("Y-m-d H:i:s", $old_time ).
		  // "\n\$new_time: ".date("Y-m-d H:i:s", $new_time )."</code></pre>\n";
		  // $duration = intval(( $new_time - $old_time )) . ".0";
      $duration = intval(( $new_time - $old_time ));
		  $repeater = substr( $oldline, strpos( $oldline, "at " ) + 3, strpos( $oldline, " ", strpos( $oldline, "at " ) + 3) - strpos( $oldline, "at " ) + 3 );
		  $loss = substr( $line, 75, strpos( $line, "%", 75 ) - 74 );
      if( $loss == "0%" ) {
        $loss = "-x-";
      }
		  $ber = substr( $line, 96, strpos( $line, "%", 96 ) - 95 );
      if( $ber == "0.0%" ) $ber = "-x-";
  	} else {
  		continue;
  	}
      // echo "<pre><code>\$callsign: $callsign at \$dgid: $dgid\n\$old_time: ".date("Y-m-d H:i:s", $old_time ).
      //   "\n\$new_time: ".date("Y-m-d H:i:s", $new_time )."</code></pre>\n";

  	// echo "<pre><code>OLD LINE: $oldline\nLINE: $line\n</code></pre>\n";

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

  	// Lastly we set $oldline as the actual line
  	$oldline = $line;
  }

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

