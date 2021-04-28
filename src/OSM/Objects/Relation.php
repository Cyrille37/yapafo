<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Tools\Logger;

/**
 * Description of OSM_Relation
 *
 * @author cyrille
 */
class Relation extends OSM_Object implements IXml
{
	/**
	 * @const string
	 */
	const OBJTYPE_MEMBER = 'member';

	protected $_members = array();

	public static function fromXmlObj(\SimpleXMLElement $xmlObj) {

		$relation = new Relation();

		$processedElements = $relation->_fromXmlObj($xmlObj);

		foreach ($xmlObj->children() as $child)
		{
			if (in_array($child->getName(), $processedElements))
				continue;

			Logger::getInstance()->debug('{_m} child:{child}', ['_m'=>__METHOD__, 'child'=>$child->getName()]);
			switch ($child->getName())
			{
				case self::OBJTYPE_MEMBER :
					$member = Member::fromXmlObj($child);
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
	 * @param string|Member $memberOrType
	 * @param string $ref The member's ref. Should be null if $memberOrType instanceof Member .
	 * @return string
	 */
	protected static function _memberKey($memberOrType, $ref=null) {

		if ($memberOrType instanceof Member)
		{
			if (!empty($ref))
				throw new \InvalidArgumentException('$ref must be empty');
			return $memberOrType->getType() . $memberOrType->getRef();
		}

		if (empty($ref))
			throw new \InvalidArgumentException('$ref must not be empty');
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
		return false;
	}

	public function hasMember(Member $member) {

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
	 * @return Member[]
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
			throw new \InvalidArgumentException('Invalid type "' . $type . '"');

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
			throw new \InvalidArgumentException('Invalid type "' . $type . '"');

		$members = array();
		foreach ($this->_members as $member)
		{
			if ($member->getType() == $type && $member->getRole() == $role)
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

		$k = self::_memberKey($memberType, $refId);
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

		return $this->getMember(OSM_Api::OBJTYPE_NODE, $nodeId);
	}

	/**
	 *
	 * @param string $nodeId
	 * @return Member 
	 */
	public function getMemberWay($wayId) {

		return $this->getMember(OSM_Api::OBJTYPE_WAY, $wayId);
	}

	/**
	 * Add a node as a new member in the relation.
	 * 
	 * @param Node $node
	 * @param type $role
	 * @return Relation Fluent interface
	 */
	public function addNode(Node $node, $role='') {

		$member = new Member(OSM_Api::OBJTYPE_NODE, $node->getId(), $role);
		$this->addMember($member);
		return $this;
	}

	/**
	 *
	 * @param Way $way
	 * @param type $role
	 * @return Relation Fluent interface
	 */
	public function addWay(Way $way, $role='') {

		$member = new Member(OSM_Api::OBJTYPE_WAY, $way->getId(), $role);
		$this->addMember($member);
		return $this;
	}

	/**
	 *
	 * @param Member $member
	 * @return Relation Fluent interface
	 */
	public function addMember(Member $member) {

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
	 * @return Relation Fluent interface
	 */
	public function addMembers(array $members) {

		if (!is_array($members) || count($members) == 0)
			throw new OSM_Exception('members array is empty');

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
		$this->setDirty();
		return $this;
	}

	/**
	 *
	 * @param Member $member 
	 */
	public function removeMember(Member $member) {

		if (!$this->hasMember($member))
			throw new OSM_Exception('Member ' . self::_memberKey($member) . ' not found');
		unset($this->_members[self::_memberKey($member)]);
		$this->setDirty();
	}

}
