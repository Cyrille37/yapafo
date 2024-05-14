#!/usr/bin/env php
<?php
/**
 * Query data with Overpass API.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;

$osmapi = new OSM_Api([
]);

//$query = '*[place=*][name=Artins][@meta]' ;
$query = 'node[amenity=hospital][bbox=-6,50,2,61]';
//$query = '*[landuse=residential][residential=halting_site]' ;

$res = $osmapi->queryXApi( $query );

_dbg( 'Result: '.$osmapi->getXmlDocument() );
_dbg( 'Found '.count( $osmapi->getObjects() ).' objects' );
