<?php

/**
 * OSM/Tag.php
 */

/**
 * Description of OSM_Tag
 *
 * @author cyrille
 */
class OSM_Objects_Tag implements OSM_Objects_IDirty, OSM_Objects_IXml {

	protected $_key;
	protected $_value;

	/**
	 * @var bool
	 */
	protected $_dirty = true;

	/**
	 * @param SimpleXMLElement $xmlObj
	 * @return OSM_Objects_Tag 
	 */
	public static function fromXmlObj(SimpleXMLElement $xmlObj) {

		$tag = new OSM_Objects_Tag( (string) $xmlObj['k'], (string) $xmlObj['v'] );
		$tag->setDirty(false);
		return $tag ;
	}

	/**
	 * @return string 
	 */
	public function asXmlStr()
	{
		$xmlStr = '<tag k="'.$this->_key.'" v="'.$this->_value.'" />' ;
		return $xmlStr ;
	}

	/**
	 * @param type $key
	 * @param type $value
	 */
	public function __construct($key, $value) {

		//OSM_ZLog::debug(__METHOD__, 'Create a Tag ', $key, '=', $value);

		$this->_key = $key;
		$this->_value = $value;
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return $this->_key;
	}

	/**
	 * @return string
	 */
	public function getValue() {
		return $this->_value;
	}

	/**
	 * @param string $value 
	 */
	public function setValue($value) {
		$this->_value = $value;
		$this->setDirty();
	}

	public function isDirty() {
		return $this->_dirty;
	}
	
	public function setDirty($dirty=true)
	{
		$this->_dirty = $dirty ;
	}

}
