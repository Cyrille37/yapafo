<?php

namespace Cyrille37\OSM\Yapafo\Objects;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception;
use Cyrille37\OSM\Yapafo\Tools\Logger;

/**
 * Description of OSM_Object
 *
 * @author cyrille
 */
class OSM_Object implements IDirty
{
	const OBJTYPE_TAG = 'tag';
	const OBJTYPE_ND = 'nd';
	const OBJTYPE_NODE = 'node';
	const OBJTYPE_WAY = 'way';
	const OBJTYPE_RELATION = 'relation';
	const OBJTYPE_MEMBER = 'member';

	const ATTR_VERSION = 'version';
	const ATTR_TIMESTAMP = 'timestamp';
	const ATTR_CHANGESET = 'changeset';
	const ATTR_UID = 'uid';
	const ATTR_USER = 'user';

	const ACTION_DELETE = 'delete';
	const ACTION_MODIFY = 'modify';

	/**
	 * @var array
	 */
	protected $_attrs = array();

	/**
	 * @var array
	 */
	protected $_tags = array();

	/**
	 * @var bool
	 */
	protected $_dirty = true;
	protected $_deleted = false;

	/**
	 * @param string $id
	 */
	public function __construct($id = null)
	{
		if ($id != null)
			$this->setId($id);
		$this->setDirty();
	}

	public function getId()
	{

		return $this->_attrs['id'];
	}

	public function setId($id)
	{

		//if (!empty($this->_attrs['id']))
		//	throw new OSM_Exception('Could not change Id, only set it.');
		//throw new OSM_Exception('Could not set positive Id, only negative.');

		$this->_attrs['id'] = $id;
	}

	public function getVersion()
	{
		return $this->_attrs[self::ATTR_VERSION] ?? null;
	}

	public function getTimestamp()
	{
		return $this->_attrs[self::ATTR_TIMESTAMP] ?? null;
	}

	public function getChangeset()
	{
		return $this->_attrs[self::ATTR_CHANGESET] ?? null;
	}
	public function getUid()
	{
		return $this->_attrs[self::ATTR_UID] ?? null;
	}
	public function getUser()
	{
		return $this->_attrs[self::ATTR_USER] ?? null;
	}

	/**
	 * @return string
	 */
	public function getObjectType()
	{
		switch (get_class($this)) {
			case Node::class:
				return self::OBJTYPE_NODE;
			case Way::class:
				return self::OBJTYPE_WAY;
			case Relation::class:
				return self::OBJTYPE_RELATION;
			default:
				throw new OSM_Exception('Unknow type "' . get_class($this) . '"');
		}
	}

	/**
	 * @return bool
	 */
	public function isDirty()
	{

		if ($this->_dirty)
			return true;
		foreach ($this->_tags as $t)
			if ($t->isDirty())
				return true;
		return false;
	}

	/**
	 * @param bool $dirty
	 */
	public function setDirty($dirty = true)
	{

		$this->_dirty = $dirty;

		if ($dirty) {
			// 'action' attribute is need by the osm file format.
			if ($this->_deleted) {
				$this->_attrs["action"] = self::ACTION_DELETE;
			} else {
				$this->_attrs["action"] = self::ACTION_MODIFY;
			}
		} else {
			$this->_deleted = false;
		}
	}

	public function delete()
	{
		$this->_deleted = true;
		$this->setDirty();
	}

	/**
	 * @return bool
	 */
	public function isDeleted()
	{
		return $this->_deleted;
	}

	/**
	 * Retreive a tag by Key.
	 *
	 * A value can be provided for behavior like `hasTag()`.
	 *
	 * @param string $key
	 * @param string $v Optional. If not provided or if an empty string or a '*' the value will not be tested.
	 * @return Tag
	 */
	public function getTag($key, $v = null)
	{

		if (array_key_exists($key, $this->_tags)) {
			if ($v && ($v != '*')) {
				if ($this->_tags[$key]->getValue() == $v)
					return $this->_tags[$key];
				return null;
			}
			return $this->_tags[$key];
		}
		return null;
	}

	public function hasTags(array $tags)
	{
		foreach( $tags as $tagName => $tagValue )
		{
			if( ! $this->getTag($tagName, $tagValue) )
				return false ;
		}
		return true ;
	}

	/**
	 * 
	 * @return array<Tag>
	 */
	public function getTags()
	{
		return $this->_tags;
	}

	/**
	 * Set a Tag value.
	 * Failed if Tag does not exists.
	 * @throws OSM_Exception if Tag does not exists.
	 */
	public function setTag($key, $value)
	{
		if (!array_key_exists($key, $this->_tags))
			throw new OSM_Exception('Tag "' . $key . '" not found');
		$this->_tags[$key]->setValue($value);
		$this->setDirty();
	}

	/**
	 * Add a Tag.
	 * @param Tag|string $tagOrKey
	 * @param string value
	 * @throws OSM_Exception if Tag does already exists.
	 */
	public function addTag($tagOrKey, $value = null)
	{

		if ($tagOrKey instanceof Tag) {
			if (array_key_exists($tagOrKey->getKey(), $this->_tags)) {
				throw new OSM_Exception('duplicate tag "' . $tagOrKey->getKey() . '"');
			}
			$tag = $tagOrKey;
		} else {
			if (array_key_exists($tagOrKey, $this->_tags)) {
				throw new OSM_Exception('duplicate tag "' . $tagOrKey . '"');
			}
			$tag = new Tag($tagOrKey, $value);
		}
		$this->_tags[$tag->getKey()] = $tag;
		$this->setDirty();
	}

	public function addOrUpdateTag(string $key, $value = null)
	{
		if (array_key_exists($key, $this->_tags)) {
			$this->setTag($key, $value);
		} else {
			$this->addTag($key, $value);
		}
	}

	public function addTags(array $tags)
	{
		foreach ($tags as $key => $value) {
			$this->addTag($key, $value);
		}
	}

	public function removeTag($key)
	{

		if (!array_key_exists($key, $this->_tags))
			throw new OSM_Exception('Tag "' . $key . '" not found');
		unset($this->_tags[$key]);
		$this->setDirty();
	}

	public function getAttribute($key)
	{

		if (array_key_exists($key, $this->_attrs))
			return $this->_attrs[$key];
		return null;
	}

	public function getAttributes()
	{
		return $this->_attrs;
	}

	public function setAttribute($key, $value)
	{

		return $this->_attrs[$key] = $value;
		$this->setDirty();
	}

	/**
	 *
	 * @param \SimpleXMLElement $xmlObj
	 * @return array List of processed children types to avoid reprocessing in sub class.
	 */
	protected function _fromXmlObj(\SimpleXMLElement $xmlObj)
	{

		foreach ($xmlObj->attributes() as $k => $v) {
			$this->_attrs[(string) $k] = (string) $v;
		}

		if (!array_key_exists('id', $this->_attrs))
			throw new OSM_Exception(__CLASS__ . ' should must a "id" attribute');

		Logger::getInstance()->debug('{_m} {class} {id}', ['_m' => __METHOD__, 'class' => __CLASS__, 'id' => $this->getId()]);

		foreach ($xmlObj->children() as $child) {
			switch ($child->getName()) {
				case self::OBJTYPE_TAG:
					Logger::getInstance()->debug('{_m} found child {child}', ['_m' => __METHOD__, 'child' => self::OBJTYPE_TAG]);
					$tag = Tag::fromXmlObj($child);
					$this->_tags[$tag->getKey()] = $tag;
					break;
			}
		}

		return array(self::OBJTYPE_TAG);
	}
}
