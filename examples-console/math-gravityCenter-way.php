#!/usr/bin/env php
<?php
/**
 * Compute a way's Gravity Center.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Objects\Way ;

$osmApi = new OSM_Api([
    'url' => OSM_Api::URL_PROD_UK.OSM_Api::URL_PATH_API,
    // Because we change the Url, have to erase potential Access Token in Config
    'access_token' => null,
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
