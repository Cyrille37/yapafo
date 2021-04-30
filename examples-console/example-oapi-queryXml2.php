#!/usr/bin/env php
<?php
/**
 * Query data with Overpass API.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Psr\Log\LogLevel;

$xmlQuery = '
<osm-script>
    <query type="relation">
        <has-kv k="admin_level" v="8" />
        <has-kv k="name" v="Artins" />
    </query>
    <recurse type="relation-relation" into="rr" />
    <recurse type="relation-way" into="rw" />
    <recurse type="relation-node" into="rn" />
    <recurse type="way-node" into="wn" />
    <union>
        <item set="rr" />
        <item set="rw" />
        <item set="rn" />
        <item set="wn" />
    </union>
    <print/>
</osm-script>
';

$osmapi = new OSM_Api([
    //'log'=>['level'=>LogLevel::NOTICE]
]);

/**
 * @var \Cyrille37\OSM\Yapafo\OApiResponse $res
 */
$osmapi->queryOApiXml( $xmlQuery );

_dbg( 'Result: '.$osmapi->getXmlDocument() );
_dbg( 'Found '.count( $osmapi->getObjects() ).' objects' );
