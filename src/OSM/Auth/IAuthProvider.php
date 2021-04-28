<?php
namespace Cyrille37\OSM\Yapafo\Auth ;

/**
 * 
 */

/**
 *
 * @author cyrille
 */
interface IAuthProvider {

	public function addHeaders(&$headers, $url, $method);
}
