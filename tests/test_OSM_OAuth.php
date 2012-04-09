#!/usr/bin/php
<?php
/*
 * http://wiki.openstreetmap.org/wiki/OAuth
 * 
 * Réglages:
 * - $DEV=true pour utiliser le serveur de test api06.dev.openstreetmap.org
 * - $DEV=false pour le serveur de prod www.openstreetmap.org
 * 
 * Test en 2 phases.
 * 1. supprimé le fichier "*.token" (eg. tests/test_OSM_Auth_OAuth.php.token)
 * 2. lancer tests/test_OSM_Auth_OAuth.php et suivre les instructions
 * 3. relancer tests/test_OSM_Auth_OAuth.php qui va réutiliser le token obtenu (stocké dans "*.token" )
 * 		en étape 2 pour accéder aux données de l'utilisateur.
 */

$DEV = true;

$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
_wl('test "' . basename(__FILE__) . '');

require_once (__DIR__ . '/../lib/OSM/Api.php');

/**
 * Détails OAuth pour OAuth_essais01 (PROD)
 * 
 * http://www.openstreetmap.org/user/Cyrille37/oauth_clients/499
 * 
 * Clé de l'utilisateur : K0Fc6En9GulO9nBrJ6Bz7ltHcZRL9vD3kqDMaX8V
 * Secret de l'utilisateur : HRv7OOGCA2bKYcfw1Jlbg8nCXodHOCSAAHhY1XU4
 * 
 * Demande des permission suivantes de l'utilisateur :
 * 	modifier la carte.
 * Nous supportons hamc-sha1 (recommandé) et texte brut en mode ssl.
 */
$OAUTH_COMSUMERKEY = 'K0Fc6En9GulO9nBrJ6Bz7ltHcZRL9vD3kqDMaX8V';
$OAUTH_COMSUMERSECRET = 'HRv7OOGCA2bKYcfw1Jlbg8nCXodHOCSAAHhY1XU4';

/**
 * Détails OAuth pour OAuth_essais01 (DEV)
 * 
 * http://api06.dev.openstreetmap.org/user/Cyrille37_TEST/oauth_clients/1217
 * 
 * Clé de l'utilisateur : K0Fc6En9GulO9nBrJ6Bz7ltHcZRL9vD3kqDMaX8V
 * Secret de l'utilisateur : HRv7OOGCA2bKYcfw1Jlbg8nCXodHOCSAAHhY1XU4
 * 
 * Demande des permission suivantes de l'utilisateur :
 * 	modifier la carte.
 * Nous supportons hamc-sha1 (recommandé) et texte brut en mode ssl.
 */
$OAUTH_COMSUMERKEY_DEV = 'T7qXv9xVzFFqhbIbygEnu0MB0uchtmTuaDbz6WcK';
$OAUTH_COMSUMERSECRET_DEV = 'VtJnCvwzdE8rVNeAukLAYd1YxqeWCQD3W4xLeU1Z';

//
//
//

if ($DEV)
{
	$oauth = new OSM_Auth_OAuth($OAUTH_COMSUMERKEY_DEV, $OAUTH_COMSUMERSECRET_DEV, array(
			'requestTokenUrl' => OSM_Auth_OAuth::REQUEST_TOKEN_URL_DEV,
			'accessTokenUrl' => OSM_Auth_OAuth::ACCESS_TOKEN_URL_DEV,
			'authorizeUrl' => OSM_Auth_OAuth::AUTHORIZE_TOKEN_URL_DEV
		));
	$apiUrl = 'http://api06.dev.openstreetmap.org/api/0.6';
}
else
{

	$oauth = new OSM_Auth_OAuth($OAUTH_COMSUMERKEY, $OAUTH_COMSUMERSECRET);
	$apiUrl = 'http://www.openstreetmap.org/api/0.6';
}

$tokenFilename = __DIR__ . '/' . basename(__FILE__) . '.token';

if (file_exists($tokenFilename))
{
	echo 'Reusing Authorization...' . "\n";

	$fp = fopen($tokenFilename, 'r');
	eval('$authCredentials = ' . file_get_contents($tokenFilename) . ';');
	fclose($fp);

	$oauth->setToken($authCredentials['token'], $authCredentials['tokenSecret']);

	// Get user details (GET)

	$result = $oauth->http($apiUrl . '/user/details');
	echo 'User details: ' . print_r($result, true) . "\n";

	// Ask a changeSet (PUT)
	
	$xmlStr = "<?xml version='1.0' encoding=\"UTF-8\"?>\n" .
		'<osm version="0.6" generator="DEV OAUTH">'
		. '<changeset id="0" open="false">'
		. '<tag k="created_by" v="DEV OAUTH http://www.openstreetmap.org/user/Cyrille37"/>'
		. '<tag k="comment" v="test test"/>'
		. '</changeset></osm>';
	$result = $oauth->http($apiUrl . '/changeset/create','PUT', $xmlStr);
	echo 'Changeset create: ' . print_r($result, true) . "\n";
}
else
{
	echo 'Requesting Authorization...' . "\n";

	$authCredentials = $oauth->requestAuthorizationUrl();
	$authUrl = $authCredentials['url'];

	//echo 'Goto "' . $authUrl . '&callback=' . urlencode('http://osm7.openstreetmap.fr/~cgiquello/dummyCallback.php') . '"' . "\n";
	echo 'Goto "' . $authUrl . '"' . "\n";
	echo 'waiting you coming back ...' . "\n";
	flush();
	$handle = fopen("php://stdin", "r");
	$line = fgets($handle);

	echo 'Requesting an Access token.' . "\n";

	$authCredentials = $oauth->requestAccessToken();

	echo 'Saving credentials in "' . $tokenFilename . '"' . "\n";

	$fp = fopen($tokenFilename, 'w');
	fwrite($fp, var_export($authCredentials, true));
	fclose($fp);
}


echo 'done.' . "\n";
