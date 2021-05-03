#!/usr/bin/env php
<?php
/**
 * Transform OSM Xml to CSV
 */

require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Tools\OsmXmlToCsv;
use Psr\Log\LogLevel;

$osmapi = new OSM_Api([
    'log'=>['level'=>LogLevel::WARNING]
]);

$obj = $osmapi->getRelation( 10 );
$obj = $osmapi->getWay( 1000 );
$obj = $osmapi->getNode( 1000 );

OsmXmlToCsv::toCsv( $osmapi, STDOUT );
