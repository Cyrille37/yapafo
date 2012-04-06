<?php

/**
 * OSM/OAPIResponse.class.php
 */

/**
 * Description of OSM_OApiResponse
 *
 * Providess some accessors to the OAPI result. All stuff are SimpleXMLElement objects.
 * 
 * @author cyrille
 */
class OSM_OApiResponse {

	public static $DEBUG = 0;
	protected $_xml;
	protected $_relations, $_ways, $_nodes;
	protected $_index = array(
		'relations' => null,
		'ways' => null,
		'nodes' => null
	);

	public function __construct($xmlStr) {
		//_dbg(''.$xml->getName());
		$this->_xml = new SimpleXMLElement($xmlStr);
	}

	/**
	 * @return SimpleXMLElement 
	 */
	public function getRoot() {
		return $this->_xml;
	}

	/**
	 * Return XML or write XML into a file.
	 * 
	 * @param string $filename If specified, the function writes the data to the file rather than returning it.
	 * @return mixed 
	 */
	public function asXML($filename=null) {
		if( $filename==null)
			return $this->getRoot()->asXML();
		else
			return $this->getRoot()->asXML($filename);
	}

	/**
	 * @return SimpleXMLElement[] 
	 */
	public function getRelations() {

		if ($this->_relations == null)
		{
			$this->_relations = $this->_xml->xpath('/osm/relation');
			$this->dbg(__METHOD__, 'found ' . count($this->_relations) . ' relations');
		}
		return $this->_relations;
	}

	/**
	 *
	 * @param string|int $relation_id
	 * @return SimpleXMLElement 
	 */
	public function getRelation($relation_id) {

		if ($this->_index['relations'] == null)
		{
			$this->_index['relations'] = array();
			foreach ($this->getRelations() as $relation)
			{
				$attrs = $relation->attributes();
				$this->_index['relations'][(string) $attrs['id']] = $relation;
			}
			$this->dbg(__METHOD__, 'Created index for ' . count($this->_index['relations']) . ' relations');
		}

		if (array_key_exists($relation_id, $this->_index['relations']))
		{
			return $this->_index['relations'][$relation_id];
		}

		return null;
	}

	/**
	 * @param string|SimpleXMLElement $relationIdOrObj
	 * @return SimpleXMLElement[]
	 */
	public function &getRelationWays($relationIdOrObj) {

		if (is_a($relationIdOrObj, 'SimpleXMLElement'))
		{
			$relation = $relationIdOrObj;
		}
		else
		{
			$relation = $this->getRelation((string) $relationIdOrObj);
		}
		$ways = array();
		foreach ($relation->xpath('member[@type="way"]') as $member)
		{
			$mas = $member->attributes();
			$wayId = (string) $mas['ref'];
			$ways[$wayId] = $this->getWay($wayId);
		}
		return $ways;
	}

	/**
	 * @param string|SimpleXMLElement $relationIdOrObj
	 * @return SimpleXMLElement[]
	 */
	public function &getRelationNodes($relationIdOrObj) {
		if (is_a($relationIdOrObj, 'SimpleXMLElement'))
		{
			$relation = $relationIdOrObj;
		}
		else
		{
			$relation = $this->getRelation((string) $relationIdOrObj);
		}
		$nodes = array();
		foreach ($relation->xpath('member[@type="node"]') as $member)
		{
			$mas = $member->attributes();
			$nodeId = (string) $mas['ref'];
			$nodes[$nodeId] = $this->getNode($nodeId);
		}
		return $nodes;
	}

	/**
	 * @param string|int $way_id
	 * @return SimpleXMLElement 
	 */
	public function getWay($way_id) {
		if ($this->_index['ways'] == null)
		{
			$this->_index['ways'] = array();
			foreach ($this->getWays() as $way)
			{
				$attrs = $way->attributes();
				$this->_index['ways'][(string) $attrs['id']] = $way;
			}
			$this->dbg(__METHOD__, 'Created index for ' . count($this->_index['ways']) . ' ways');
		}
		if (array_key_exists($way_id, $this->_index['ways']))
		{
			return $this->_index['ways'][$way_id];
		}
		return null;
	}

	/**
	 * @param string|SimpleXMLElement $wayIdOrObj
	 * @return SimpleXMLElement[] 
	 */
	public function &getWayNodes($wayIdOrObj) {

		if (is_a($wayIdOrObj, 'SimpleXMLElement'))
		{
			$way = $wayIdOrObj;
		}
		else
		{
			$way = $this->getWay((string) $wayIdOrObj);
		}
		$nodes = array();
		foreach ($way->xpath('nd') as $nd)
		{
			$nas = $nd->attributes();
			$nodeId = (string) $nas['ref'];
			$nodes[$nodeId] = $this->getNode($nodeId);
		}
		return $nodes;
	}

	/**
	 * @param string|int $node_id
	 * @return SimpleXMLElement
	 */
	public function getNode($node_id) {
		if ($this->_index['nodes'] == null)
		{
			$this->_index['nodes'] = array();
			foreach ($this->getNodes() as $node)
			{
				$attrs = $node->attributes();
				$this->_index['nodes'][(string) $attrs['id']] = $node;
			}
			$this->dbg(__METHOD__, 'Created index for ' . count($this->_index['nodes']) . ' nodes');
		}
		if (array_key_exists($node_id, $this->_index['nodes']))
		{
			return $this->_index['nodes'][$node_id];
		}
		return null;
	}

	/**
	 * @return SimpleXMLElement[]
	 */
	public function getWays() {

		if ($this->_ways == null)
		{
			$this->_ways = $this->_xml->xpath('/osm/way');
			$this->dbg(__METHOD__, 'found ' . count($this->_ways) . ' ways');
		}
		return $this->_ways;
	}

	/**
	 * @return SimpleXMLElement[]
	 */
	public function getNodes() {

		if ($this->_nodes == null)
		{
			$this->_nodes = $this->_xml->xpath('/osm/node');
			$this->dbg(__METHOD__, 'found ' . count($this->_nodes) . ' nodes');
		}
		return $this->_nodes;
	}

	/**
	 * $tags is a array of Key=>Value.
	 * 
	 * @param array $tags
	 * @return array
	 */
	public function getNodesByTags( array $tags )
	{
		// Sélection d'éléments en fonction des attributs de leurs enfants :
		//$selectedNodes = $xmlObj->xpath('/osm/node[tag/@k="ref:INSEE"][tag/@v="37001"]');

		$xq = '' ;
		foreach( $tags as $k=>$v )
		{
			// 'member[@type="node"][@role="admin_centre"]'
			$xq .= '[tag/@k="'.$k.'"][tag/@v="'.$v.'"]';
		}
		return $this->_xml->xpath('/osm/node'.$xq );
	}


	/**
	 * @param string $who
	 * @param string $str 
	 */
	public function dbg($who, $str='') {
		if (self::$DEBUG)
		{
			echo '[dbg][' . $who . '] ' . $str . "\n";
		}
	}

}
