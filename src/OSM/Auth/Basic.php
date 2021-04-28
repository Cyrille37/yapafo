<?php
namespace Cyrille37\OSM\Yapafo\Auth ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Basic
 *
 * @author cyrille
 */
class Basic implements IAuthProvider {

	protected $_user;
	protected $_password;

	public function __construct($user, $password) {
		
		if (empty($user))
			throw new OSM_Exception('Credential "user" must be set');
		
		$this->_user = $user;
		$this->_password = $password;
	}

	public function addHeaders(&$headers, $url=null, $method=null) {

		$headers[] = 'Authorization: Basic ' . base64_encode($this->_user . ':' . $this->_password);
	}

}
