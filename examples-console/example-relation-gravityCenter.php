#!/usr/bin/env php
<?php
/**
 * Compute a relation's Gravity Center.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Objects\Relation ;

$osmApi = new OSM_Api([
    'url' => OSM_Api::URL_PROD_UK
    //'log'=>['level'=>LogLevel::NOTICE]
]);

// https://www.openstreetmap.org/relation/4619331
$relId = 4619331 ;
/**
 * @var Relation $rel
 */
$rel = $osmApi->getRelation( $relId, true );

_dbg( 'Found '.count( $osmApi->getObjects() ).' objects' );
_dbg( 'Result: '.print_r($rel->getGravityCenter( $osmApi ),true) );
