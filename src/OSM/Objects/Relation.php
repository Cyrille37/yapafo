<?php

namespace Cyrille37\OSM\Yapafo\Objects;

use Cyrille37\OSM\Yapafo\Tools\Polygon;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception;
use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Tools\Config;
use Cyrille37\OSM\Yapafo\Tools\Logger;

/**
 * Description of OSM_Relation
 *
 * @author cyrille
 */
class Relation extends OSM_Object implements IXml
{
	const ROLE_OUTER = 'outer';

	const OPTION_OSM_RELATION_DUPLICATE_AUTHORISED = 'osm_relation_duplicate_authorised';

	/**
	 * Member's ref are not unique !
	 * @var array<Member>
	 */
	protected $_members = [];
	/**
	 * @var array{keys: array<array<int>>, types: array<array<int>>, roles: array<array<int>>}
	 */
	protected $_indexes = [
		'keys' => [],
		'types' => [],
		'roles' => [],
	];

	public static function fromXmlObj(\SimpleXMLElement $xmlObj)
	{
		$relation = new Relation();

		$relation->_fromXmlObj($xmlObj);

		// Only processing "member" child
		foreach ($xmlObj->children() as $child) {
			Logger::getInstance()->debug('{_m} child:{child}', ['_m' => __METHOD__, 'child' => $child->getName()]);
			switch ($child->getName()) {
				case self::OBJTYPE_MEMBER:
					$member = Member::fromXmlObj($child);
					$relation->addMember($member);
					break;

				default:
					//throw new OSM_Exception('Object "' . $child->getName() . '" is not supported in relation');
			}
		}

		$relation->setDirty(false);
		return $relation;
	}

	/**
	 * @return string
	 */
	public function asXmlStr()
	{

		//$xmlName = strtolower(str_replace('OSM_Objects_', '', $this->get_class()));
		$xmlName = 'relation';

		$xmlStr = '<' . $xmlName;
		foreach ($this->_attrs as $a => $v) {
			$xmlStr .= ' ' . $a . '="' . $v . '"';
		}
		$xmlStr .= '>' . "\n";
		foreach ($this->_tags as $k => $tag) {
			$xmlStr .= $tag->asXmlStr() . "\n";
		}
		foreach ($this->_members as $member) {
			$xmlStr .= $member->asXmlStr() . "\n";
		}

		$xmlStr .= '</' . $xmlName . '>';

		return $xmlStr;
	}

	/**
	 * Compute the member's key into the relation members collection.
	 *
	 * @param string|Member $memberOrType
	 * @param string $ref The member's ref. Should be null if $memberOrType instanceof Member .
	 * @return string
	 */
	protected static function _memberKey($memberOrType, $ref = null)
	{
		if ($memberOrType instanceof Member) {
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

	public static function isValidMemberType($memberType)
	{
		switch ($memberType) {
			case OSM_Object::OBJTYPE_RELATION:
			case OSM_Object::OBJTYPE_WAY:
			case OSM_Object::OBJTYPE_NODE:
				return true;
		}
		return false;
	}

	public function isDirty()
	{
		if (parent::isDirty())
			return true;
		foreach ($this->_members as $m)
			if ($m->isDirty())
				return true;
		return false;
	}

	public function hasMember(Member $member)
	{
		if (isset($this->_indexes['keys'][self::_memberKey($member)]))
			return true;
		return false;
	}

	/**
	 *
	 * @return array<Member>
	 */
	public function getMembers()
	{
		return $this->_members;
	}

	/**
	 * Find members of a certain role.
	 *
	 * @param string $role
	 * @return Member[]
	 */
	public function findMembersByRole($role, $unique = true)
	{
		$members = [];
		if (isset($this->_indexes['roles'][$role])) {
			foreach ($this->_indexes['roles'][$role] as $idx) {
				$members[] = $this->_members[$idx];
			}
		}
		return $members;
	}

	/**
	 * Find members of a certain type.
	 *
	 * @param string $type
	 * @return Member[]
	 * @throws {@link InvalidArgumentException} if invalid type.
	 */
	public function &findMembersByType($type)
	{
		if (!self::isValidMemberType($type))
			throw new \InvalidArgumentException('Unkow type "' . $type . '"');

		$members = [];
		if (isset($this->_indexes['types'][$type])) {
			foreach ($this->_indexes['types'][$type] as $idx) {
				$members[] = $this->_members[$idx];
			}
		}
		return $members;
	}

	/**
	 * Find members of a certain type and a certain role.
	 *
	 * @param string $type
	 * @param string $role
	 * @return Member[]
	 * @throws {@link InvalidArgumentException} if invalid type.
	 */
	public function &findMembersByTypeAndRole($type, $role)
	{
		if (!self::isValidMemberType($type))
			throw new \InvalidArgumentException('Invalid type "' . $type . '"');

		$idxType = $this->_indexes['types'][$type];
		$idxRole = $this->_indexes['roles'][$role];
		$idx = array_intersect($idxType, $idxRole);

		$members = [];
		foreach ($idx as $i) {
			$members[] = $this->_members[$i];
		}
		return $members;
	}

	public function &findMembersDuplicate()
	{
		$members = [];
		foreach ($this->_indexes['keys'] as $key => $idx) {
			if (count($idx) > 1)
			{
				$roles = [];
				foreach( $idx as $i )
				{
					$r = $this->_members[$i]->getRole();
					if( isset($roles, $r) )
					{
						$members[] = $this->_members[$i];
						break;
					}
					$roles[$r] = 1 ;
				}
			}
		}
		return $members;
	}

	/**
	 *
	 * @param string $memberType
	 * @param string $nodeId
	 * @return Member|array<Member>
	 */
	public function getMember($memberType, $refId, $first = true)
	{
		$k = self::_memberKey($memberType, $refId);
		if (isset($this->_indexes['keys'][$k])) {
			$idx = $this->_indexes['keys'][$k];
			if ($first)
				return $this->_members[$idx[0]];
			$members = [];
			foreach ($idx as $i)
				$members[] = $this->_members[$i];
			return $members;
		}
		return null;
	}

	/**
	 *
	 * @param string $nodeId
	 * @return Member
	 */
	public function getMemberNode($nodeId)
	{
		return $this->getMember(OSM_Object::OBJTYPE_NODE, $nodeId);
	}

	/**
	 *
	 * @param string $nodeId
	 * @return Member
	 */
	public function getMemberWay($wayId)
	{
		return $this->getMember(OSM_Object::OBJTYPE_WAY, $wayId);
	}

	/**
	 * Add a node as a new member in the relation.
	 *
	 * @param Node $node
	 * @param type $role
	 * @return Relation Fluent interface
	 */
	public function addNode(Node $node, $role = '')
	{
		$member = new Member(OSM_Object::OBJTYPE_NODE, $node->getId(), $role);
		$this->addMember($member);
		return $this;
	}

	/**
	 *
	 * @param Way $way
	 * @param type $role
	 * @return Relation Fluent interface
	 */
	public function addWay(Way $way, $role = '')
	{
		$member = new Member(OSM_Object::OBJTYPE_WAY, $way->getId(), $role);
		$this->addMember($member);
		return $this;
	}

	public static function isDuplicateAuthorised()
	{
		return boolval(Config::get(self::OPTION_OSM_RELATION_DUPLICATE_AUTHORISED));
	}

	/**
	 *²
	 * @param Member $member
	 * @return Relation Fluent interface
	 */
	public function addMember(Member $member)
	{
		$mKey = self::_memberKey($member);
		if ((! self::isDuplicateAuthorised()) && $this->hasMember($member)) {
			$members = $this->getMember($member->getType(), $member->getRef(), false);
			foreach ($members as $m) {
				if ($m->getRole() == $member->getRole())
					throw new OSM_Exception('duplicate member "' . $member->getType() . '/' . $member->getRef() . '" with role "' . $member->getRole() . '" in relation "' . $this->getId() . '"');
			}
		}
		$this->_members[] = $member;
		//$idx = count($this->_members) - 1;
		$idx = array_key_last($this->_members);
		// Key index
		if (! isset($this->_indexes['keys'][$mKey]))
			$this->_indexes['keys'][$mKey] = [$idx];
		else
			$this->_indexes['keys'][$mKey][] = $idx;
		// Role index
		if (! isset($this->_indexes['roles'][$member->getRole()]))
			$this->_indexes['roles'][$member->getRole()] = [$idx];
		else
			$this->_indexes['roles'][$member->getRole()][] = $idx;
		// Type index
		if (! isset($this->_indexes['types'][$member->getType()]))
			$this->_indexes['types'][$member->getType()] = [$idx];
		else
			$this->_indexes['types'][$member->getType()][] = $idx;
		$this->setDirty();
		return $this;
	}

	/**
	 *
	 * @param array $members
	 * @return Relation Fluent interface
	 */
	public function addMembers(array $members)
	{
		if (empty($members))
			throw new OSM_Exception('members array is empty');

		foreach ($members as $member) {
			$this->addMember($member);
		}
		return $this;
	}

	/**
	 *
	 * @param Member $member
	 * @param string|null $role
	 */
	public function removeMember(Member $member, $role = null)
	{
		if (!$this->hasMember($member))
			throw new OSM_Exception('Member ' . self::_memberKey($member) . ' not found in relation "' . $this->getId() . '"');
		//unset($this->_members[self::_memberKey($member)]);

		$idx = $this->_indexes['keys'][self::_memberKey($member)];

		foreach ($idx as $i) {
			foreach ($this->_indexes['roles'] as $role => $a) {
				foreach ($a as $j => $v) {
					if ($v == $i)
						unset($this->_indexes['roles'][$role][$j]);
				}
			}
			foreach ($this->_indexes['types'] as $type => $a) {
				foreach ($a as $j => $v) {
					if ($v == $i)
						unset($this->_indexes['types'][$type][$j]);
				}
			}
		}
		$this->setDirty();
	}

	/**
	 * @return Polygon
	 */
	public function getPolygon(OSM_Api $osmApi)
	{
		$poly = new Polygon();
		foreach ($this->findMembersByTypeAndRole(OSM_Object::OBJTYPE_WAY, self::ROLE_OUTER) as $key => $member) {
			$way = $osmApi->getWay($member->getRef(), true);
			foreach ($way->getNodesRefs() as $nodeRef) {
				$node = $osmApi->getNode($nodeRef);
				$poly->addv($node->getLat(), $node->getLon());
			}
		}
		return $poly;
	}

	public function getGravityCenter(OSM_Api $osmApi)
	{
		return $this->getPolygon($osmApi)->getGravityCenter();
	}
}
