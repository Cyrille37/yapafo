#!/usr/bin/env php
<?php
/**
 * Query data on Overpass API with QL language.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Psr\Log\LogLevel;

$qlQuery = '
area[admin_level=3][name="France métropolitaine "]->.country;
area[admin_level=8][name="Tours"](area.country)->.ville;
(
 node(area.ville)[amenity~"^(bar|pub)$"];
);
out ;';

$osmapi = new OSM_Api([
    //'log'=>['level'=>LogLevel::NOTICE]
]);

/**
 * @var \Cyrille37\OSM\Yapafo\OApiResponse $res
 */
$osmapi->queryOApiQL( $qlQuery );

_dbg( 'Result: '.$osmapi->getXmlDocument() );
_dbg( 'Stats: '.print_r($osmapi->getStats(),true ) );
