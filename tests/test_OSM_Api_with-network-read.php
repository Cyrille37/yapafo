#!/usr/bin/php
<?php

$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
require_once (__DIR__ . '/../lib/OSM/Api.php');

_wl('test OSM_Api with network');

$osmApi = new OSM_Api();

$xmlQuery = '
<osm-script>
<union>
 <query type="relation" into="qr">
  <has-kv k="boundary" v="administrative"/>
  <has-kv k="admin_level" v="8"/>
  <has-kv k="ref:INSEE" v="37001"/>
 </query>
 <recurse type="relation-node" from="qr"/>
 <recurse type="relation-way" from="qr"/>
 <recurse type="way-node"/>
</union>
<print />
</osm-script>
';
$osmApi->queryOApiGet($xmlQuery);

// getRelations, getWays, getNodes

$relations = $osmApi->getRelations();
_assert(count($relations) == 1);
$ways = $osmApi->getWays();
_assert(count($ways) == 8);
$nodes = $osmApi->getNodes();
_assert(count($nodes) == 680);
$objects = $osmApi->getObjects();
_assert(count($objects) == 689);

// getRelation, getWay, getNode

$relation = $osmApi->getRelation('164211');
_assert($relation != null);
_assert($relation->isDirty() == false);
$way = $osmApi->getWay('34717700');
_assert($way != null);
_assert($way->isDirty() == false);
$node = $osmApi->getNode('691558211');
_assert($node != null);
_assert($node->isDirty() == false);

// getObjectsByTags

$objects = $osmApi->getObjectsByTags(array('ref:INSEE' => '37001'));
_assert(count($objects) == 2);
$objects = $osmApi->getObjectsByTags(array('ref:INSEE' => ''));
_assert(count($objects) == 2);
$objects = $osmApi->getObjectsByTags(array('ref:INSEE' => '','place' => ''));
_assert(count($objects) == 1);

// test removeObject

$osmApi->removeObject(OSM_Api::OBJTYPE_NODE, '691558211');
$node = $osmApi->hasNode('691558211');
_assert($node == null);

$time_end = microtime(true);
_wl('Test well done in ' . number_format($time_end - $time_start, 3) . ' second(s).');
