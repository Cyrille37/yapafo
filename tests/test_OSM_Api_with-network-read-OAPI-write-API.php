#!/usr/bin/php
<?php
/**
 * Read with the OAPI
 * Write with the API (on the DEV Server)
 * Select the Auth method (look at comment "Authentification")
 * 
 */
$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
_wl('test "' . basename(__FILE__) . '');

_wl('');
_wl('*** ATTENTION');
_wl('*** ATTENTION : this test work on the production server !');
_wl('*** ATTENTION');
_wl('');

require_once (__DIR__ . '/../lib/OSM/Api.php');

// ===================================================
// Authentification
// 
// Override above parameters into you own file:
include (__DIR__ . '/../../secrets_prod.php');
//
// ===================================================

$osmApi = new OSM_Api(array(
		'simulation' => false,
		//'log' => array('level' => OSM_ZLog::LEVEL_DEBUG)
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

// http://www.openstreetmap.org/api/0.6/node/1326928399

$xmlQuery = '
<osm-script>
 <query type="node">
  <has-kv k="name" v="Castel Fleuri"/>
  <has-kv k="tourism" v="hotel"/>
  <has-kv k="addr:street" v="Rue Groison"/>
  <has-kv k="addr:city" v="Tours"/>
 </query>
 <print/>
</osm-script>
';
$osmApi->queryOApi($xmlQuery);
$nodes = $osmApi->getNodes();
_assert( count($nodes)==1 );

$tagName = 'yapafo.net::test::oapi' ;

if( ($node=$nodes[0]->getTag($tagName))!=null )
{
	$nodes[0]->removeTag($tagName);
}
else
{
	$nodes[0]->addTag($tagName,'123');	
}
$osmApi->saveChanges('Cyrille37 test');

$time_end = microtime(true);
_wl('Test well done in ' . number_format($time_end - $time_start, 3) . ' second(s).');
