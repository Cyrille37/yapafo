<?php

/**
 * OSM/Member.php
 */

/**
 * Description of OSM_Member
 *
 * @author cyrille
 */
class OSM_Objects_Member implements OSM_Objects_IDirty, OSM_Objects_IXml {

	/**
	 * @var string OSM_Api::OBJTYPE_*
	 */
	protected $_type;

	/**
	 * @var string
	 */
	protected $_ref;

	/**
	 * @var string 
	 */
	protected $_role = '';

	/**
	 * @var bool
	 */
	protected $_dirty = true;

	/**
	 *
	 * @param string $type
	 * @param string $ref
	 * @param string $role 
	 */
	public function __construct($type, $ref, $role='') {

		OSM_ZLog::debug(__METHOD__, 'Create a Member ', $type, '=', $ref, ' role=', $role);

		if (empty($type))
			throw new OSM_Exception('Type could not be empty');
		if (empty($ref))
			throw new OSM_Exception('Ref could not be empty');

		$this->_type = $type;
		$this->_ref = $ref;
		$this->_role = $role;
	}

	/**
	 *
	 * @param SimpleXMLElement $xmlObj
	 * @return OSM_Objects_Member 
	 */
	public static function fromXmlObj(SimpleXMLElement $xmlObj) {

		$member = new OSM_Objects_Member((string) $xmlObj['type'], (string) $xmlObj['ref'], isset($xmlObj['role']) ? (string) $xmlObj['role'] : null);
		$member->setDirty(false);
		return $member;
	}

	/**
	 * @return string 
	 */
	public function asXmlStr() {

		$xmlStr = '<member type="' . $this->_type . '" ref="' . $this->_ref . '" role="' . $this->_role . '" />';
		return $xmlStr;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->_type;
	}

	/**
	 * @return string
	 */
	public function getRef() {
		return $this->_ref;
	}

	/**
	 * @return string
	 */
	public function getRole() {
		return $this->_role;
	}

	/**
	 *
	 * @param string $role
	 */
	public function setRole($role) {
		$this->_role = $role;
		$this->setDirty();
	}

	public function isDirty() {
		return $this->_dirty;
	}

	public function setDirty($dirty=true) {
		$this->_dirty = $dirty;
	}

}
