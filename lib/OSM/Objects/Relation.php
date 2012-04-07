<?php

/**
 * OSM/Relation.php
 */

/**
 * Description of OSM_Relation
 *
 * @author cyrille
 */
class OSM_Objects_Relation extends OSM_Objects_Object implements OSM_Objects_IXml {
	/**
	 * @const string
	 */
	const OBJTYPE_MEMBER = 'member';

	protected $_members = array();

	public static function fromXmlObj(SimpleXMLElement $xmlObj) {

		$relation = new OSM_Objects_Relation();

		$processedElements = $relation->_fromXmlObj($xmlObj);

		foreach ($xmlObj->children() as $child)
		{
			if (in_array($child->getName(), $processedElements))
				continue;

			OSM_ZLog::debug(__METHOD__, 'Found child: ', $child->getName());
			switch ($child->getName())
			{
				case self::OBJTYPE_MEMBER :
					$member = OSM_Objects_Member::fromXmlObj($child);
					$relation->addMember($member);
					break;

				default:
					throw new OSM_Exception('Object "' . $xmlObj->getName() . '" is not supported in relation');
			}
		}

		$relation->setDirty(false);
		return $relation;
	}

	/**
	 * @return string 
	 */
	public function asXmlStr() {

		//$xmlName = strtolower(str_replace('OSM_Objects_', '', $this->get_class()));
		$xmlName = 'relation';

		$xmlStr = '<' . $xmlName;
		foreach ($this->_attrs as $a => $v)
		{
			$xmlStr.= ' ' . $a . '="' . $v . '"';
		}
		$xmlStr.='>' . "\n";
		foreach ($this->_tags as $k => $tag)
		{
			$xmlStr.= $tag->asXmlStr() . "\n";
		}
		foreach ($this->_members as $member)
		{
			$xmlStr.= $member->asXmlStr() . "\n";
		}

		$xmlStr.= '</' . $xmlName . '>';

		return $xmlStr;
	}

	/**
	 * Compute the member's key into the relation members collection.
	 * 
	 * @param string|OSM_Objects_Member $memberOrType
	 * @param string $ref The member's ref. Should be null if $memberOrType instanceof OSM_Objects_Member .
	 * @return string
	 */
	protected static function _memberKey($memberOrType, $ref=null) {

		if ($memberOrType instanceof OSM_Objects_Member)
		{
			if (!empty($ref))
				throw new InvalidArgumentException('$ref must be empty');
			return $memberOrType->getType() . $memberOrType->getRef();
		}

		if (empty($ref))
			throw new InvalidArgumentException('$ref must not be empty');
		if (!self::isValidMemberType($memberOrType))
			throw new OSM_Exception('Invalid member type "' . $memberOrType . '"');
		return $memberOrType . $ref;
	}

	public static function isValidMemberType($memberType) {

		switch ($memberType)
		{
			case OSM_Api::OBJTYPE_WAY:
			case OSM_API::OBJTYPE_NODE:
				return true;
		}
		return false;
	}

	public function isDirty() {

		if (parent::isDirty())
			return true;
		foreach ($this->_members as $m)
			if ($m->isDirty())
				return true;
		return $this->_dirty;
	}

	public function hasMember(OSM_Objects_Member $member) {

		if (array_key_exists(self::_memberKey($member), $this->_members))
		{
			return true;
		}
		return false;
	}

	/**
	 *
	 * @return array
	 */
	public function getMembers() {
		return $this->_members;
	}

	/**
	 * Find members of a certain role.
	 *
	 * @param string $role
	 * @return OSM_Objects_Member[]
	 */
	public function &findMembersByRole($role) {

		$members = array();
		foreach ($this->_members as $member)
		{
			if ($member->getRole() == $role)
				$members[] = $member;
		}
		return $members;
	}

	/**
	 * Find members of a certain type.
	 * 
	 * @param string $type
	 * @return OSM_Objects_Member[]
	 * @throws {@link InvalidArgumentException} if invalid type.
	 */
	public function &findMembersByType($type) {

		if (!self::isValidMemberType($type))
			throw new InvalidArgumentException('Invalid type "' . $type . '"');

		$members = array();
		foreach ($this->_members as $member)
		{
			if ($member->getType() == $type)
				$members[] = $member;
		}
		return $members;
	}

	/**
	 * Find members of a certain type and a certain role.
	 * 
	 * @param string $type
	 * @param string $role 
	 * @return OSM_Objects_Member[]
	 * @throws {@link InvalidArgumentException} if invalid type.
	 */
	public function &findMembersByTypeAndRole($type, $role) {
		if (!self::isValidMemberType($type))
			throw new InvalidArgumentException('Invalid type "' . $type . '"');

		$members = array();
		foreach ($this->_members as $member)
		{
			if ($member->getType() == $type && $member->getRole() == $role )
				$members[] = $member;
		}
		return $members;
	}

	/**
	 *
	 * @param string $memberType
	 * @param string $nodeId
	 * @return OSM_Objects_Member 
	 */
	public function getMember($memberType, $refId) {

		$k = self::_memberKey($memberType, $nodeId);
		if (array_key_exists($k, $this->_members))
		{
			return $this->_members[$k];
		}
		return null;
	}

	/**
	 *
	 * @param string $nodeId
	 * @return OSM_Objects_Member 
	 */
	public function getMemberNode($nodeId) {

		return $this->getMemer(OSM_Api::OBJTYPE_NODE, $nodeId);
	}

	/**
	 *
	 * @param string $nodeId
	 * @return OSM_Objects_Member 
	 */
	public function getMemberWay($wayId) {

		return $this->getMemer(OSM_Api::OBJTYPE_WAY, $nodeId);
	}

	/**
	 * Add a node as a new member in the relation.
	 * 
	 * @param OSM_Objects_Node $node
	 * @param type $role
	 * @return OSM_Objects_Relation Fluent interface
	 */
	public function addNode(OSM_Objects_Node $node, $role='') {

		$member = new OSM_Objects_Member(OSM_Api::OBJTYPE_NODE, $node->getId(), $role);
		$this->addMember($member);
		return $this;
	}

	/**
	 *
	 * @param OSM_Objects_Way $way
	 * @param type $role
	 * @return OSM_Objects_Relation Fluent interface
	 */
	public function addWay(OSM_Objects_Way $way, $role='') {

		$member = new OSM_Objects_Member(OSM_Api::OBJTYPE_WAY, $way->getId(), $role);
		$this->addMember($member);
		return $this;
	}

	/**
	 *
	 * @param OSM_Objects_Member $member
	 * @return OSM_Objects_Relation Fluent interface
	 */
	public function addMember(OSM_Objects_Member $member) {

		if ($this->hasMember($member))
		{
			throw new OSM_Exception('duplicate member "' . $member->getRef() . '" of type "' . $member->getType() . '"');
		}
		$this->_members[self::_memberKey($member)] = $member;
		$this->setDirty();
		return $this;
	}

	/**
	 *
	 * @param array $members
	 * @return OSM_Objects_Relation Fluent interface
	 */
	public function addMembers(array $members) {

		foreach ($members as $member)
		{
			if ($this->hasMember($member))
			{
				throw new OSM_Exception('duplicate member "' . $member->getRef() . '" of type "' . $member->getType() . '"');
			}
		}
		foreach ($members as $member)
		{
			$this->_members[self::_memberKey($member)] = $member;
		}
		return $this;
	}

	/**
	 *
	 * @param OSM_Objects_Member $member 
	 */
	public function removeMember(OSM_Objects_Member $member) {

		if (!$this->hasMember($member))
			throw new OSM_Exception('Member ' . self::_memberKey($member) . ' not found');
		unset($this->_members[self::_memberKey($member)]);
		$this->setDirty();
	}

}
