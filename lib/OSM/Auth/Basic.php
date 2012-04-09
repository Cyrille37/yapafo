<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Basic
 *
 * @author cyrille
 */
class OSM_Auth_Basic implements OSM_Auth_IAuthProvider {

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
