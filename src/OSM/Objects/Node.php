<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;

/**
 * Description of OSM_Node
 *
 * @author cyrille
 */
class Node extends OSM_Object implements IXml {

	public static function fromXmlObj( \SimpleXMLElement $xmlObj)
	{
		$node = new Node();
		$processedElements = $node->_fromXmlObj($xmlObj);

		if (!array_key_exists('lat', $node->_attrs))
			throw new OSM_Exception(__CLASS__ . ' should have a "lat" attribute');
		if (!array_key_exists('lon', $node->_attrs))
			throw new OSM_Exception(__CLASS__ . ' should have a "lon" attribute');

		$node->setDirty(false);
		return $node;
	}

	public function __construct($id=null, $lat=null, $lon=null, array $tags=null)
	{
		parent::__construct($id);

		if ($lat != null)
			$this->setLat($lat);
		if ($lon != null)
			$this->setLon($lon);
		if (is_array($tags))
			$this->addTags($tags);
		
		$this->setDirty();
	}

	/**
	 * @return string 
	 */
	public function asXmlStr() {

		//$xmlName = strtolower(str_replace('OSM_Objects_', '', $this->get_class()));
		$xmlName = 'node';

		$xmlStr = '<' . $xmlName;
		foreach ($this->_attrs as $a => $v)
		{
			$xmlStr.= ' ' . $a . '="' . $v . '"';
		}
		if (count($this->_tags) > 0)
		{
			$xmlStr.='>' . "\n";
			foreach ($this->_tags as $k => $tag)
			{
				$xmlStr.= $tag->asXmlStr() . "\n";
			}
			$xmlStr.='</node>' . "\n";
		}
		else
		{
			$xmlStr.='/>' . "\n";
		}

		return $xmlStr;
	}

	public function getLat() {
		return $this->_attrs['lat'];
	}

	public function setLat($lat) {
		$this->_attrs['lat'] = $lat;
		$this->setDirty();
	}

	public function getLon() {
		return $this->_attrs['lon'];
	}

	public function setLon($lon) {
		$this->_attrs['lon'] = $lon;
		$this->setDirty();
	}

}
