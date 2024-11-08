<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Tools\Polygon ;
use Cyrille37\OSM\Yapafo\OSM_Api;

class Way extends OSM_Object implements IXml
{
	protected $_nodeRefs = array();

	public static function fromXmlObj(\SimpleXMLElement $xmlObj) {

		$way = new Way();
		$processedElements = $way->_fromXmlObj($xmlObj);

		foreach ($xmlObj->children() as $child)
		{
			if (in_array($child->getName(), $processedElements))
				continue;

			//OSM_ZLog::debug(__METHOD__, 'Found child: ', $child->getName());
			switch ($child->getName())
			{
				case self::OBJTYPE_ND :
					$way->addNodeRef((string) $child['ref']);
					break;

				default:
					throw new OSM_Exception('Object "' . $xmlObj->getName() . '" is not supported in relation');
			}
		}

		$way->setDirty(false);
		return $way;
	}

	/**
	 * @return string
	 */
	public function asXmlStr() {

		//$xmlName = strtolower(str_replace('OSM_Objects_', '', $this->get_class()));
		$xmlName = 'way';

		$xmlStr = '<' . $xmlName;
		foreach ($this->_attrs as $a => $v)
		{
			$xmlStr.= ' ' . $a . '="' . str_replace(['"','&','<'], ['&quot;','&amp;','&lt;'],$v) . '"';
		}
		$xmlStr.='>' . "\n";
		foreach ($this->_tags as $k => $tag)
		{
			$xmlStr.= $tag->asXmlStr() . "\n";
		}
		foreach ($this->_nodeRefs as $nodeRef)
		{
			$xmlStr.= '<nd ref="' . $nodeRef . '" />' . "\n";
		}
		$xmlStr.= '</' . $xmlName . '>';

		return $xmlStr;
	}

	/**
	 * @param int $nodeId
	 */
	public function addNodeRef($nodeId) {

		$this->_nodeRefs[] = $nodeId;
		$this->setDirty();
	}

	public function addNodes(array $nodes) {

		foreach ($nodes as $node)
		{
			if (in_array($node->getId(), $this->_nodeRefs))
			{
				throw new OSM_Exception('duplicate node ref "' . $node->getId() . '"');
			}
		}
		foreach ($nodes as $node)
		{
			$this->_nodeRefs[] = $node->getId();
		}
		$this->setDirty();
	}

	public function removeNodeRef($nodeId) {

		if (($i = array_search($nodeId, $this->_nodeRefs) ) == false)
			throw new OSM_Exception('NodeRef ' . $nodeId . ' not found');
		unset($this->_nodeRefs[$i]);
		$this->setDirty();
	}

	/**
	 *
	 * @return array
	 */
	public function getNodesRefs() {
		return $this->_nodeRefs;
	}

	/**
	 *
	 * @return int Node id
	 */
	public function getFirstNodeRef() {
		return $this->_nodeRefs[0];
	}

	/**
	 *
	 * @return int Node id
	 */
	public function getLastNodeRef() {
		return $this->_nodeRefs[count($this->_nodeRefs) - 1];
	}

	/**
	 * Is this way a closed way (closed polyline) ?
	 *
	 * In a closed way the last node of the way is identical with the first node.
	 * A closed way may be interpreted either as a closed polyline, or as an area, or both, depending on its tags.
	 *
	 * https://wiki.openstreetmap.org/wiki/Way
	 *
	 * @return boolean
	 */
	public function isClosedWay()
	{
		return( $this->getFirstNodeRef() == $this->getLastNodeRef() );
	}

	/**
	 * @return Polygon
	 */
	public function getPolygon( OSM_Api $osmApi )
	{
		$poly = new Polygon();
		foreach( $this->_nodeRefs as $id )
		{
			$node = $osmApi->getNode($id);
			$poly->addv( $node->getLat(), $node->getLon() );
		}
		return $poly ;
	}

	public function getGravityCenter( OSM_Api $osmApi )
	{
		try
		{
			return $this->getPolygon( $osmApi )->getGravityCenter();
		}
		catch(\Exception $ex )
		{
		}
		return null ;
	}
}
