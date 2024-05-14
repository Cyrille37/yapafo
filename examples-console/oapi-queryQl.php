#!/usr/bin/env php
<?php
/**
 * Query data with Overpass API.
 */
require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\OSM_OApi ;


$qlQuery = '
area[admin_level=3][name="France métropolitaine "]->.country;
area[admin_level=8][name="Tours"](area.country)->.ville;
(
 node(area.ville)[amenity~"^(bar|pub)$"];
);
out ;';

$osmOapi = new OSM_OApi();

/**
 * @var \Cyrille37\OSM\Yapafo\OApiResponse $res
 */
$res = $osmOapi->request( $qlQuery );

_dbg( $res->asXML() );
