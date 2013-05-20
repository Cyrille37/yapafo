#!/usr/bin/php
<?php

$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');

$inputFile = 'compress.zlib://'.__DIR__ . '/OApiResult01.xml.gz';
_wl('test "'.  basename(__FILE__).'" using file "'.$inputFile.'"');

require_once (__DIR__ . '/../lib/OSM/OApi.php');
require_once (__DIR__ . '/../lib/OSM/OApiResponse.php');

$xml = file_get_contents($inputFile);
$result = new OSM_OApiResponse($xml);

// getRelations, getWays, getNodes

$relations = $result->getRelations();
_assert(count($relations) == 1);
$ways = $result->getWays();
_assert(count($ways) == 8);
$nodes = $result->getNodes();
_assert(count($nodes) == 680);

// getRelation, getWay, getNode

$relation = $result->getRelation('164211');
_assert($relation != null);
$way = $result->getWay('34717700');
_assert($way != null);
$node = $result->getNode('691558211');
_assert($node != null);


//<tag k="source" v="cadastre-dgi-fr source : Direction Générale des Impôts - Cadastre ; mise à jour : 2008"/>
$nodes = $result->getNodesByTags(array(
	'source' => 'cadastre-dgi-fr source : Direction Générale des Impôts - Cadastre ; mise à jour : 2008'
	));
_assert(count($nodes) == 106);

$nodes = $result->getNodesByTags(array(
	'ref:INSEE' => '37001'
	));
_assert(count($nodes) == 1);

$time_end = microtime(true);
_wl('Test well done in '.  number_format($time_end-$time_start,3).' second(s).');
