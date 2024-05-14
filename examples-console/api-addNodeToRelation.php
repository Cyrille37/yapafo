#!/usr/bin/env php
<?php
/**
 * Create a Node and add it to a Relation.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Objects\Relation;
use Cyrille37\OSM\Yapafo\Objects\Node;

$osmapi = new OSM_Api();

_wl('Using URLs: Read=' . $osmapi->getOption('url').', Write='.$osmapi->getOption('url4Write'));

try
{
	/**
	 * @var Node $node
	 * @var Relation $relation
	 */

	// Create a node with a tag
	$lat = 47.39982 . rand(0, 10);
	$lon = 0.68889 . rand(0, 10);
	$node = $osmapi->addNewNode($lat, $lon, ['toto'=>'essai Cyrille37']);

	// retrieve relation
	$id = '10';
	$relation = $osmapi->getRelation($id, true);

	// add node to relation
	$relation->addNode($node, 'dummyRole');

	// save in a changeset
	$osmapi->saveChanges('essais de ' . date('c'));
}
catch (OSM_Exception $ex)
{
	$osmapi->getLogger()->error('Main: '.' An error occured: '.$ex->getMessage());
}
