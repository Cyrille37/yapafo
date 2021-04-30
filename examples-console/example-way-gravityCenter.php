#!/usr/bin/env php
<?php
/**
 * Compute a way's Gravity Center.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Objects\Way ;

$osmApi = new OSM_Api([
    'url' => OSM_Api::URL_PROD_UK
    //'log'=>['level'=>LogLevel::NOTICE]
]);

// https://www.openstreetmap.org/way/51444249
$wayId = 51444249 ;
/**
 * @var Way $way
 */
$way = $osmApi->getWay( $wayId, true );

_dbg( 'Found '.count( $osmApi->getObjects() ).' objects' );
_dbg( 'Result: '.print_r($way->getGravityCenter( $osmApi ),true) );
