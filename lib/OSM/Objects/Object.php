<?php

/**
 * OSM/Objects/Object.php
 */

/**
 * Description of OSM_Object
 *
 * @author cyrille
 */
class OSM_Objects_Object implements OSM_Objects_IDirty {
	/**
	 * 
	 */
	const OBJTYPE_TAG = 'tag';

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
	public function __construct($id=null) {

		if( $id!=null && $id!='' && $id!=0 )
			$this->setId($id);
	}

	public function getId() {

		return $this->_attrs['id'];
	}

	public function setId($id) {

		//if (!empty($this->_attrs['id']))
		//	throw new OSM_Exception('Could not change Id, only set it.');
		//throw new OSM_Exception('Could not set positive Id, only negative.');

		$this->_attrs['id'] = $id;
	}

	public function getVersion() {

		return $this->_attrs['version'];
	}

	/**
	 * @return bool 
	 */
	public function isDirty() {

		foreach ($this->_tags as $t)
			if ($t->isDirty())
				return true;
		return $this->_dirty;
	}

	/**
	 * @param bool $dirty 
	 */
	public function setDirty($dirty=true) {
		$this->_dirty = $dirty;
	}

	public function delete() {
		$this->_deleted = true;
		$this->setDirty();
	}

	/**
	 * @return bool
	 */
	public function isDeleted() {
		return $this->_deleted;
	}

	/**
	 * @param string $key
	 * @return OSM_Objects_Tag
	 */
	public function getTag($key) {

		if (array_key_exists($key, $this->_tags))
		{
			return $this->_tags[$key];
		}
		return null;
	}

	public function getTags()
	{
		return $this->_tags ;
	}

	public function setTag($key, $value) {
		if (!array_key_exists($key, $this->_tags))
			throw new OSM_Exception('Tag "' . $key . '" not found');
		$this->_tags[$key]->setValue($value);
	}

	/**
	 * @param OSM_Objects_Tag $tag
	 */
	public function addTag(OSM_Objects_Tag $tag) {

		if (array_key_exists($tag->getKey(), $this->_tags))
		{
			throw new OSM_Exception('duplicate tag "' . $tag->getKey() . '"');
		}
		$this->_tags[$tag->getKey()] = $tag;
		$this->setDirty();
	}

	public function addTags(array $tags) {
		if (!is_array($tags))
			throw new OSM_Exception('Invalid array of tags');
		foreach ($tags as $tag)
		{
			if (array_key_exists($tag->getKey(), $this->_tags))
			{
				throw new OSM_Exception('duplicate tag "' . $tag->getKey() . '"');
			}
		}
		foreach( $tags as $tag )
		{
			$this->addTag($tag);
		}
	}

	public function removeTag($key) {

		if (!array_key_exists($key, $this->_tags))
			throw new OSM_Exception('Tag "' . $key . '" not found');
		unset($this->_tags[$key]);
		$this->setDirty();
	}

	public function getAttribute($key) {

		if (array_key_exists($key, $this->_attrs))
			return $this->_attrs[$key];
		return null;
	}

	public function setAttribute($key, $value) {

		return $this->_attrs[$key] = $value;
		$this->setDirty();
	}

	/**
	 *
	 * @param SimpleXMLElement $xmlObj
	 * @return array List of processed children types to avoid reprocessing in sub class.
	 */
	protected function _fromXmlObj(SimpleXMLElement $xmlObj) {

		foreach ($xmlObj->attributes() as $k => $v)
		{
			$this->_attrs[(string) $k] = (string) $v;
		}

		if (!array_key_exists('id', $this->_attrs))
			throw new OSM_Exception(__CLASS__ . ' should must a "id" attribute');

		OSM_ZLog::debug(__METHOD__, 'Got a ' . __CLASS__ . ' with id=', $this->getId());

		foreach ($xmlObj->children() as $child)
		{
			switch ($child->getName())
			{
				case OSM_Objects_Object::OBJTYPE_TAG :
					OSM_ZLog::debug(__METHOD__, 'Found child: ', OSM_Objects_Object::OBJTYPE_TAG);
					$tag = OSM_Objects_Tag::fromXmlObj($child);
					$this->_tags[$tag->getKey()] = $tag;
					break;
			}
		}

		return array(OSM_Objects_Object::OBJTYPE_TAG);
	}

}
