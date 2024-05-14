#!/usr/bin/env php
<?php

require_once(__DIR__.'/example-common.php');

use Cyrille37\OSM\Yapafo\Tools\Math ;

$paris= [48.856667, 2.350987];
$lyon= [45.767299, 4.834329];
echo 'Paris-Lyon on fltt straight line : ', Math::vectorLength($paris[0], $paris[1],$lyon[0], $lyon[1] ), "\n";
echo 'Paris-Lyon on earth: ', Math::distanceOnEarth($paris[0], $paris[1],$lyon[0], $lyon[1] ), "\n";
