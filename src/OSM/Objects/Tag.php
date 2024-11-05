<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;

/**
 * Description of OSM_Tag
 *
 * @author cyrille
 */
class Tag implements IDirty, IXml
{
	/**
	 * @var string
	 */
	protected $_key;
	/**
	 * @var string
	 */
	protected $_value;

	/**
	 * @var bool
	 */
	protected $_dirty = true;

	/**
	 * @param SimpleXMLElement $xmlObj
	 * @return Tag 
	 */
	public static function fromXmlObj( \SimpleXMLElement $xmlObj) {

		$tag = new Tag( (string) $xmlObj['k'], (string) $xmlObj['v'] );
		$tag->setDirty(false);
		return $tag ;
	}

	/**
	 * @return string 
	 */
	public function asXmlStr()
	{
		$xmlStr = '<tag k="'.$this->_key.'" v="'.str_replace(['"','&'], ['&quot;','&amp;'], $this->_value ).'" />' ;
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
