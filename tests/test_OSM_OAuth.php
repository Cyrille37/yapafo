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
 *
 * Note: files "secrets.php" and "test_OSM_OAuth.php.token" are ignored by Git (see .gitignore)
 */

$DEV = true;

$time_start = microtime( true );

require_once(__DIR__ . '/tests_common.php');
_wl( 'test "' . basename( __FILE__ ) . '' );

require_once(__DIR__ . '/../lib/OSM/Api.php');

include_once(__DIR__ . '/secrets.php');

if( $DEV )
{
	$apiUrl = 'http://api06.dev.openstreetmap.org/api/0.6';
	$oauth = new OSM_Auth_OAuth( $AUTH_OAUTH_CONSUMER_KEY_DEV, $AUTH_OAUTH_CONSUMER_SECRET_DEV,
			array(
					'requestTokenUrl' => OSM_Auth_OAuth::REQUEST_TOKEN_URL_DEV,
					'accessTokenUrl' => OSM_Auth_OAuth::ACCESS_TOKEN_URL_DEV,
					'authorizeUrl' => OSM_Auth_OAuth::AUTHORIZE_TOKEN_URL_DEV
			) );
}
else
{
	$apiUrl = 'http://www.openstreetmap.org/api/0.6';
	$oauth = new OSM_Auth_OAuth( $AUTH_OAUTH_CONSUMER_KEY_PROD, $AUTH_OAUTH_CONSUMER_SECRET_PROD );
}

$tokenFilename = __DIR__ . '/' . basename( __FILE__ ) . '.token';

if( file_exists( $tokenFilename ) )
{
	echo 'Reusing Authorization...' . "\n";

	$fp = fopen( $tokenFilename, 'r' );
	eval( '$authCredentials = ' . file_get_contents( $tokenFilename ) . ';' );
	fclose( $fp );

	$oauth->setAccessToken( $authCredentials['token'], $authCredentials['tokenSecret'] );

	/*
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
	 */

	$osmApi = new OSM_Api( array(
		'url' => $apiUrl
	) );
	$osmApi->setCredentials( $oauth );
	$userDetails = $osmApi->getUserDetails();
	$userDetails = $userDetails->getDetails();
	echo print_r( $userDetails );

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
	$handle = fopen( "php://stdin", "r" );
	$line = fgets( $handle );

	echo 'Requesting an Access token.' . "\n";

	$authCredentials = $oauth->requestAccessToken();

	echo 'Saving credentials in "' . $tokenFilename . '"' . "\n";

	$fp = fopen( $tokenFilename, 'w' );
	fwrite( $fp, var_export( $authCredentials, true ) );
	fclose( $fp );
}

echo 'done.' . "\n";
