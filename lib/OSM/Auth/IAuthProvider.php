<?php
/**
 * 
 */

/**
 *
 * @author cyrille
 */
interface OSM_Auth_IAuthProvider {

	public function addHeaders(&$headers, $url, $method);
}
