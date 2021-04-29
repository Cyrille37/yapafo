#!/usr/bin/env php
<?php

require_once( __DIR__.'/../vendor/autoload.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Auth\OAuth ;
use Cyrille37\OSM\Yapafo\Tools\Config ;

writeLine('OSM API configuration:');
writeLine('- osm_api_url: '.Config::get('osm_api_url'));
writeLine('OSM OAUTH configuration:');
writeLine('- oauth_url: '.Config::get('oauth_url'));
if( Config::get('osm_api_consumer_key') && Config::get('osm_api_consumer_secret') )
{
    writeLine('- Ok, has consumer token.');
}
else
{
    writeLine('* Failed, no consumer token defined.');
}
if( Config::get('osm_api_token') && Config::get('osm_api_secret') )
{
    writeLine('- Ok, has access token.');
}
else
{
    writeLine('* Failed, no access token defined.');
}

$oauth = new OAuth(Config::get('osm_api_consumer_key'), Config::get('osm_api_consumer_secret'), [
    'base_url' => Config::get('oauth_url')
]);
$oauth->setAccessToken(Config::get('osm_api_token'),Config::get('osm_api_secret'));
$osmApi = new OSM_Api([
    'url' => Config::get('osm_api_url')
]);
$osmApi->setCredentials( $oauth );

writeLine('API Permissions:');
writeLine('Read user preferences: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_READ_PREFS, true) ? 'allowed' : 'forbidden') );
writeLine('Write user preferences: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_PREFS) ? 'allowed' : 'forbidden') );
writeLine('Access write user diary: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_DIARY) ? 'allowed' : 'forbidden') );
writeLine('Write api: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_API) ? 'allowed' : 'forbidden') );
writeLine('Load user gpx traces: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_READ_GPX) ? 'allowed' : 'forbidden') );
writeLine('Upload user gpx traces: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_GPX) ? 'allowed' : 'forbidden') );
writeLine('Modify user map notes: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_NOTE) ? 'allowed' : 'forbidden') );

function writeLine( $msg='' )
{
    echo $msg, "\n";
}