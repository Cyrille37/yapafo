<?php

/**
 * OSM/Way.php
 */

/**
 * Description of OSM_Way
 *
 * @author cyrille
 */
class OSM_Objects_Way extends OSM_Objects_Object implements OSM_Objects_IXml {
	const OBJTYPE_ND = 'nd';

	protected $_nodeRefs = array();

	public static function fromXmlObj(SimpleXMLElement $xmlObj) {

		$way = new OSM_Objects_Way();
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
			$xmlStr.= ' ' . $a . '="' . $v . '"';
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

}
