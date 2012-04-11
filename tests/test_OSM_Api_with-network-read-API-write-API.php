#!/usr/bin/php
<?php
/**
 * Read with the API
 * Write with the API (on the DEV Server)
 * Select the Auth method (look at comment "Authentification")
 * 
 */
$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
_wl('test "' . basename(__FILE__) . '');

require_once (__DIR__ . '/../lib/OSM/Api.php');

// ===================================================
// Authentification
//
$auth_method = 'Basic';
$auth_basic_user = '';
$auth_basic_password = '';
//
$auth_method = 'OAuth';
$auth_oauth_consumer_key = '';
$auth_oauth_consumer_secret = '';
$auth_oauth_token = '';
$auth_oauth_secret = '';
//
$auth_method = '';
// Override above parameters into you own file:
include (__DIR__ . '/../../secrets.php');
//
// ===================================================

$osmApi = new OSM_Api(array(
		'url' => OSM_Api::URL_DEV_UK,
		'url4Write' => OSM_Api::URL_DEV_UK,
		'simulation' => false
	));

if ($auth_method == 'Basic')
{
	_wl(' using Basic auth with user="'.$auth_basic_user.'"');
	$osmApi->setCredentials(
		new OSM_Auth_Basic($auth_basic_user, $auth_basic_password)
	);
	
}
else if ($auth_method == 'OAuth')
{
	_wl(' using OAuth auth with consumerKey="'.$auth_oauth_consumer_key.'"');
	$oauth = new OSM_Auth_OAuth($auth_oauth_consumer_key, $auth_oauth_consumer_secret	);
	$oauth->setToken($auth_oauth_token, $auth_oauth_secret);
	$osmApi->setCredentials($oauth);
	
}

// http://api06.dev.openstreetmap.org/api/0.6/relation/500
// http://api06.dev.openstreetmap.org/api/0.6/way/8184
// http://api06.dev.openstreetmap.org/api/0.6/node/611571
// get a node

$node = $osmApi->getNode('611571');

// add a tag

$tagName = 'yapafo.net::test::' . time();
$tagValue = 'Have a nice dev ;-)';
$node->addTag(new OSM_Objects_Tag($tagName, $tagValue));
$tags = $node->findTags(array($tagName => $tagValue));
_assert(count($tags) == 1);
_assert($node->hasTags(array($tagName => $tagValue)));

// save changes

_assert($node->isDirty());
_wl(' saving changes...');
$osmApi->saveChanges('A yapafo.net test');
_assert($node->isDirty());
$tags = $node->findTags(array($tagName => $tagValue));
_assert(count($tags) == 1);
_assert($node->hasTags(array($tagName => $tagValue)));

// remove then reload object to get real changes

$osmApi->removeObject(OSM_Api::OBJTYPE_NODE, '611571');
$node = $osmApi->getNode('611571');
$tags = $node->findTags(array($tagName => $tagValue));
_assert(count($tags) == 1);
_assert($node->hasTags(array($tagName => $tagValue)));

// test a node's tag modification 

$tagName = 'yapafo.net::test::modify';
$tagValue = '1';

$osmApi->removeAllObjects();
$node = $osmApi->getNode('611571');

if (!$node->hasTags(array($tagName => null)))
{
	$node->addTag(new OSM_Objects_Tag($tagName, $tagValue));
	_wl(' saving changes...');
	$osmApi->saveChanges('A yapafo.net test');
	$osmApi->removeAllObjects();
	$node = $osmApi->getNode('611571');
}
else
{
	
}
$node->setTag($tagName, $tagValue + 1);
_assert($node->isDirty());
_wl(' saving changes...');
$osmApi->saveChanges('A yapafo.net test');
_assert($node->isDirty());
$osmApi->removeAllObjects();

$node = $osmApi->getNode('611571');
_assert(!$node->isDirty());
_assert($node->getTag($tagName, $tagValue + 1)->getValue() == '2');

// @todo ...
$relation = $osmApi->getRelation('500');
$memberRole = 'yapafo_test';
// Create a node
$node = $osmApi->addNewNode(0.1, 0.1);
$node->addTag('A yapafo.net test', 'add node');
// Add it has member to the relation
$member = new OSM_Objects_Member( OSM_Api::OBJTYPE_NODE, $node->getId(), $memberRole);
$relation->addMember($member);
$osmApi->saveChanges('A yapafo.net test');
//
// Check node exists and it's a relation's member.
//
$osmApi->removeAllObjects();
$relation = $osmApi->getRelation('500',true);
$nodes = $osmApi->getNodesByTags(array('A yapafo.net test'=>'add node'));
_assert($nodes!=null);
$members = $relation->findMembersByTypeAndRole( OSM_Api::OBJTYPE_NODE, $memberRole);
_assert($members!=null);
// remove this new member and delete the created node
foreach( $members as $member )
{
	$relation->removeMember($member);
}
$osmApi->saveChanges('A yapafo.net test');
// delete the created node
$osmApi->removeAllObjects();
$relation = $osmApi->getRelation('500',true);
foreach( $members as $member )
{
	$osmApi->getNode( $member->getRef() )->delete();
}
$osmApi->saveChanges('A yapafo.net test');
//
// Check remove members and delete object
//
$osmApi->removeAllObjects();
$relation = $osmApi->getRelation('500',true);
$nodes = $osmApi->getNodesByTags(array('A yapafo.net test'=>'add node'));
_assert(count($nodes)==0);
$members = $relation->findMembersByTypeAndRole( OSM_Api::OBJTYPE_NODE, $memberRole);
_assert(count($members)==0);

$time_end = microtime(true);
_wl('Test well done in ' . number_format($time_end - $time_start, 3) . ' second(s).');
