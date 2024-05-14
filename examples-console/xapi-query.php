#!/usr/bin/env php
<?php
/**
 * Query data with Overpass API.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_XApi ;

$query = '*[place=*][name=Artins]' ;
//$query = 'node[amenity=hospital][bbox=-6,50,2,61]';
//$query = '*[landuse=residential][residential=halting_site]' ;
$xapi = new OSM_XApi();
$res = $xapi->request( $query );

_dbg( print_r($res,true) );
