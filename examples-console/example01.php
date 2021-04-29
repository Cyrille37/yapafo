#!/usr/bin/env php
<?php
/**
 * Retrieve a Relation, a Way and a Node.
 */

require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api;

$osmapi = new OSM_Api();

$id = '10' ;
$obj = $osmapi->getRelation($id);

_dbg( 'Relation '.$id .':');
_dbg( print_r($obj,true) );

$id = '1000' ;
$obj = $osmapi->getWay($id);

_dbg( 'Way '.$id .':');
_dbg( print_r($obj,true) );

$id = '1000' ;
$obj = $osmapi->getNode($id);

_dbg( 'Node '.$id .':');
_dbg( print_r($obj,true) );
