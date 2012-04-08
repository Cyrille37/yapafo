#!/usr/bin/php
<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
_wl('test "' . basename(__FILE__) . '');

require_once (__DIR__ . '/../lib/OSM/Api.php');

$osmApi = new OSM_Api();
$node = $osmApi->getNode('611571');

$serialData = serialize($osmApi);
$osmApi = unserialize($serialData);

$time_end = microtime(true);
_wl('Test well done in ' . number_format($time_end - $time_start, 3) . ' second(s).');
