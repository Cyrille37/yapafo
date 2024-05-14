<?php
/**
 */

error_reporting(-1);

require_once(__DIR__.'/../vendor/autoload.php');

//
// Functions
//

function _w(...$str) {
  $n = count($str);
  for( $i=0; $i<$n; $i++)
  {
    echo $str[$i] , ($i+1<$n ? ' ': '' );
  }
}

function _wl($str) {
  echo $str . "\n";
}

function _dbg($str) {
  echo '[dbg] ' . $str . "\n";
}
