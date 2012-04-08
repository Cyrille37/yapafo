#!/usr/bin/php
<?php

$time_start = microtime(true);
_wl('test "' . basename(__FILE__) . '');

require_once (__DIR__ . '/tests_common.php');
require_once (__DIR__ . '/../lib/OSM/Api.php');

$osmApi = new OSM_Api();

$osmApi->getRelation('164211');

// only the relation is loaded

$relations = $osmApi->getRelations();
_assert(count($relations) == 1);
$ways = $osmApi->getWays();
_assert(count($ways) == 0);
$nodes = $osmApi->getNodes();
_assert(count($nodes) == 0);
$objects = $osmApi->getObjects();
_assert(count($objects) == 1);

// the relation and all its members are loaded

$osmApi->getRelation('164211',true);

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
