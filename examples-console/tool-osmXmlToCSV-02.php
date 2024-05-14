#!/usr/bin/env php
<?php
/**
 * Transform OSM Xml to CSV with tags filter.
 */

require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Tools\OsmXmlToCsv;
use Psr\Log\LogLevel;

$osmapi = new OSM_Api([
    'url' => OSM_Api::URL_PROD_UK.OSM_Api::URL_PATH_API,
    // Because we change the Url, have to erase potential Access Token in Config
    'access_token' => null,
    'log'=>['level'=>LogLevel::WARNING]
]);

// https://www.openstreetmap.org/relation/4619331
$osmapi->getRelation( 4619331, true );

OsmXmlToCsv::toCsv( $osmapi, STDOUT, [
    'tags' => ['landuse'=>null, 'residential'=>'halting_site']
] );
