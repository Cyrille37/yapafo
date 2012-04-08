#!/usr/bin/php
<?php
/*
 * http://wiki.openstreetmap.org/wiki/OAuth
 * 
 */
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
 *	modifier la carte.
 * Nous supportons hamc-sha1 (recommandé) et texte brut en mode ssl.
 */
$OAUTH_COMSUMERKEY = 'K0Fc6En9GulO9nBrJ6Bz7ltHcZRL9vD3kqDMaX8V';
$OAUTH_COMSUMERSECRET = 'Rv7OOGCA2bKYcfw1Jlbg8nCXodHOCSAAHhY1XU4';

/**
 * Détails OAuth pour OAuth_essais01 (DEV)
 * 
 * http://api06.dev.openstreetmap.org/user/Cyrille37_TEST/oauth_clients/1217
 * 
 * Clé de l'utilisateur : K0Fc6En9GulO9nBrJ6Bz7ltHcZRL9vD3kqDMaX8V
 * Secret de l'utilisateur : HRv7OOGCA2bKYcfw1Jlbg8nCXodHOCSAAHhY1XU4
 * 
 * Demande des permission suivantes de l'utilisateur :
 *	modifier la carte.
 * Nous supportons hamc-sha1 (recommandé) et texte brut en mode ssl.
 */
$OAUTH_COMSUMERKEY_DEV = 'T7qXv9xVzFFqhbIbygEnu0MB0uchtmTuaDbz6WcK';
$OAUTH_COMSUMERSECRET_DEV = 'VtJnCvwzdE8rVNeAukLAYd1YxqeWCQD3W4xLeU1Z';

//
//
//

$DEV = true ;

if( $DEV )
{
	$oauth = new OSM_OAuth($OAUTH_COMSUMERKEY_DEV,$OAUTH_COMSUMERSECRET_DEV,true);
}
else
{
	$oauth = new OSM_OAuth($OAUTH_COMSUMERKEY,$OAUTH_COMSUMERSECRET,false);
}

$authUrl = $oauth->requestAuthorizationUrl();
echo 'Goto "'.$authUrl.'"'."\n";
echo 'waiting you coming back ...'."\n";
flush();
$handle = fopen ("php://stdin","r");
$line = fgets($handle);

$oauth->requestAccessToken();

echo 'done.'."\n";
