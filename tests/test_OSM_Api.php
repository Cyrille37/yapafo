#!/usr/bin/php
<?php
$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
require_once (__DIR__ . '/../lib/OSM/Api.php');

$inputFile = 'compress.zlib://' . __DIR__ . '/OApiResult01.xml.gz';
_wl('test "' . basename(__FILE__) . '" using file "' . $inputFile . '"');

$xml = file_get_contents($inputFile);

$osmApi = new OSM_Api(
		array('url' => 'dummy')
);
$osmApi->createObjectsfromXml($xml);

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

$time_end = microtime(true);
_wl('Test well done in ' . number_format($time_end - $time_start, 3) . ' second(s).');
