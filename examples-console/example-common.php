<?php
/**
 */

error_reporting(-1);

require_once(__DIR__.'/../vendor/autoload.php');

//
// Functions
//

function _w($str) {
  echo $str ;
}

function _wl($str) {
  echo $str . "\n";
}

function _dbg($str) {
  echo '[dbg] ' . $str . "\n";
}
