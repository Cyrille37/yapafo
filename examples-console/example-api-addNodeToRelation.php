#!/usr/bin/env php
<?php
/**
 * Create a Node and add it to a Relation.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\Auth\OAuth;
use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Objects\Relation;
use Cyrille37\OSM\Yapafo\Objects\Node;
use Cyrille37\OSM\Yapafo\Tools\Config;

$oauth = new OAuth(Config::get('osm_api_consumer_key'), Config::get('osm_api_consumer_secret'), [
    'base_url' => Config::get('oauth_url')
]);
$oauth->setAccessToken(Config::get('osm_api_token'),Config::get('osm_api_secret'));
$osmapi = new OSM_Api([
    'url' => Config::get('osm_api_url'),
	'simulation' => false,
]);
$osmapi->setCredentials( $oauth );

_wl('Using URLs: Read=' . $osmapi->getOption('url').', Write='.$osmapi->getOption('url4Write'));

try
{
	/**
	 * @var Node $node
	 * @var Relation $relation
	 */

	// Create a node with a tag
	$lat = 58.5699100 . rand(0, 100);
	$lon = 26.2884700 . rand(0, 100);
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
	$osmapi->getLogger()->error('Main'.' An error occured: '.$ex->getMessage());
}
