#!/usr/bin/env php
<?php
/**
 * Retrieve a full Relation and add a tag to it.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\Auth\OAuth;
use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Objects\Relation;
use Cyrille37\OSM\Yapafo\Objects\Tag;
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
	$id = '10';
	_wl('Retrieving relation ' . $id . ':');
	/**
	 * @var Relation $obj
	 */
	$obj = $osmapi->getRelation($id, true);
	//_dbg(print_r($obj, true));
	_wl('Object isDirty: ' . ($obj->isDirty() ? 'true' : 'false'));

	// Add a tag
	//$obj->addTag( new Tag('toto',date('c')) );
	
	// Change a tag value
	_wl( 'Old tag value for toto=' . $obj->getTag('toto')->getValue() );
	$obj->setTag('toto', date('c'));
	_wl( 'New tag value for toto=' . $obj->getTag('toto')->getValue() );
	_wl('Object isDirty: ' . ($obj->isDirty() ? 'true' : 'false'));

	$osmapi->saveChanges('essais de ' . date('c'));

}
catch (OSM_Exception $ex)
{
	$osmapi->getLogger()->error('Main'.' An error occured: '.$ex->getMessage());
}
