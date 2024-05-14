#!/usr/bin/env php
<?php
/**
 * Display API permissions available with configured OAuth credentials.
 */

require_once( __DIR__.'/../vendor/autoload.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;

$osmApi = new OSM_Api();

echo 'API Permissions:',"\n";
echo 'Read user preferences: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_READ_PREFS, true) ? 'allowed' : 'forbidden'), "\n";
echo 'Write user preferences: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_PREFS) ? 'allowed' : 'forbidden'), "\n";
echo 'Access write user diary: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_DIARY) ? 'allowed' : 'forbidden'), "\n";
echo 'Write api: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_API) ? 'allowed' : 'forbidden'), "\n";
echo 'Load user gpx traces: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_READ_GPX) ? 'allowed' : 'forbidden'), "\n";
echo 'Upload user gpx traces: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_GPX) ? 'allowed' : 'forbidden'), "\n";
echo 'Modify user map notes: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_NOTE) ? 'allowed' : 'forbidden'), "\n";
echo 'Modifying redactions: '. ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_REDACTIONS) ? 'allowed' : 'forbidden'), "\n";
echo 'login to osm: ', ($osmApi->isAllowedTo(OSM_Api::PERMS_OPENID) ? 'allowed' : 'forbidden'), "\n";

echo 'User details: ', var_export( $osmApi->getUserDetails(), true ), "\n";

