<?php
/**
 * OSM/Api.php
 */
require_once(__DIR__ . '/ZLog.php');

spl_autoload_register(array('OSM_Api', 'autoload'));

/**
 * Class OSM_Api
 *
 * Changes:
 * 2012-03-29
 * 	- One url for reading, another one for writing ($this->_url & $this->_url4Write)
 * Doc:
 * - http://wiki.openstreetmap.org/wiki/Api
 * - http://wiki.openstreetmap.org/wiki/OsmChange
 *
 * @author cyrille
 */
class OSM_Api {

	const VERSION = '0.3';
	const USER_AGENT = 'http://yapafo.net';
	const URL_DEV_UK = 'http://api06.dev.openstreetmap.org/api/0.6';
	//deprecated: const OSMAPI_URL_PROD_PROXY_LETTUFE = 'http://beta.letuffe.org/api/0.6';
	const URL_PROD_FR = 'http://api.openstreetmap.fr/api/0.6';
	const URL_PROD_UK = 'http://api.openstreetmap.org/api/0.6';
	const OBJTYPE_NODE = 'node';
	const OBJTYPE_WAY = 'way';
	const OBJTYPE_RELATION = 'relation';

	/**
	 * Query form: http://api.openstreetmap.fr/query_form.html
	 */
	const OAPI_URL_FR = 'http://api.openstreetmap.fr/oapi/interpreter';
	const OAPI_URL_RU = 'http://overpass.osm.rambler.ru/';
	//const OAPI_URL_LETUFFE = 'http://overpassapi.letuffe.org/api/interpreter';
	const OAPI_URL_DE = 'http://www.overpass-api.de/api/interpreter';

	protected $_options = array(
		// simulation is set by default to avoid (protected against) unwanted write !
		'simulation' => true,
		'url' => self::URL_PROD_FR,
		'url4Write' => self::URL_PROD_UK,
		// to store every network communications (load/save) in a file.
		'outputFolder' => null,
		'appName' => '', // name for the application using the API
		'log' => array('level' => OSM_ZLog::LEVEL_ERROR),
		'oapi_url' => self::OAPI_URL_FR
	);
	protected $_stats = array(
		'requestCount' => 0,
		'loadedBytes' => 0
	);
	protected $_url;
	protected $_url4Write;

	/**
	 * @var OSM_Auth_IAuthProvider
	 */
	protected $_authProvider;
	/**
	 * array(
	 * 	'allow_read_prefs',		// read user preferences
	 * 	'allow_write_prefs',	// modify user preferences
	 * 	'allow_write_diary',	// create diary entries, comments and make friends
	 * 	'allow_write_api',		// modify the map
	 * 	'allow_read_gpx',			// allow_read_gpx
	 * 	'allow_write_gpx'			// upload GPS traces
	 * )
	 * 
	 * @var array 
	 */
	protected $_cachedPermissions = null;

	protected $_relations = array();
	protected $_ways = array();
	protected $_nodes = array();
	protected $_newIdCounter = -1;

	/**
	 * Works with $_options['outputFolder']. It's used to construct the filename.
	 * @var int
	 */
	protected $_outputWriteCount = 0;

	/**
	 * Store all xml Objects
	 * @var SimpleXMLElement
	 */
	protected $_loadedXml = array();

	/**
	 * autoloader
	 *
	 * @todo Is it clean to get an autoloader ? Should coder get it in an another place ?
	 * @param string $class Name of class
	 * @return boolean
	 */
	public static function autoload($class) {
		$file = __DIR__ . '/../' . str_replace('_', '/', $class) . '.php';
		//echo 'autoload search for '.$file."\n";
		if (file_exists($file))
		{
			return include_once $file;
		}
		return false;
	}

	/**
	 * @param bool $devMode Opération sur BdD de dev ou de prod. Par défaut sur l'API de DEV pour éviter les erreurs.
	 */
	public function __construct(array $options = array()) {

		// Check that all options exist then override defaults
		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, $this->_options))
				throw new OSM_Exception('Unknow ' . __CLASS__ . ' option "' . $k . '"');
			$this->_options[$k] = $v;
		}
		// Set the Logger
		OSM_ZLog::configure($this->_options['log']);

		OSM_ZLog::debug(__METHOD__, 'options: ', print_r($this->_options, true));

		// Set the Servers url

		if (empty($this->_options['url']))
		{
			throw new OSM_Exception('Option "url" must be set');
		}

		if (empty($this->_options['oapi_url']))
		{
			throw new OSM_Exception('Option "oapi_url" must be set');
		}

		if (!empty($this->_options['outputFolder']))
		{
			if (!file_exists($this->_options['outputFolder']))
			{
				throw new OSM_Exception('Option "outputFolder" is set, but the folder does not exists');
			}
		}

		OSM_ZLog::debug(__METHOD__, 'url: ' . $this->_options['url'] . ', url4Write: ' . $this->_options['url4Write']);
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getOption($key) {
		if (!array_key_exists($key, $this->_options))
			return null;
		return $this->_options[$key];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return OSM_Api fluent interface
	 */
	public function setOption($key, $value) {
		if (!array_key_exists($key, $this->_options))
			throw new OSM_Exception('Unknow Api option "' . $key . '"');
		$this->_options[$key] = $key;
		return $this;
	}

	/**
	 * @return SimpleXMLElement
	 */
	public function getLastLoadedXmlObject() {
		return simplexml_load_string($this->_loadedXml[count($this->_loadedXml) - 1]);
	}

	/**
	 *
	 * @return string
	 */
	public function getLastLoadedXmlString() {
		return $this->_loadedXml[count($this->_loadedXml) - 1];
	}

	/**
	 * @param OSM_Auth_IAuthProvider $authProvider
	 */
	public function setCredentials(OSM_Auth_IAuthProvider $authProvider) {

		$this->_authProvider = $authProvider;
	}

	/**
	 * @return OSM_Auth_IAuthProvider 
	 */
	public function getCredentials() {

		return $this->_authProvider;
	}

	protected function _httpApi($relativeUrl, $data = null, $method = 'GET') {

		$url = null;
		switch ($method)
		{
			case 'GET':
				$url = $this->_options['url'];
				break;
			case 'PUT':
			case 'POST':
				$url = $this->_options['url4Write'];
				break;
			default:
				throw new OSM_Exception('Unknow howto handle http method "' . $method . '"');
		}
		$url .= '/' . $relativeUrl;

		OSM_ZLog::notice(__METHOD__, $method, ' url: ', $url);

		$headers = array(
			// Failed with PUT :
			//'Content-type: application/x-www-form-urlencoded'
			// Works with PUT :
			//'Content-type: multipart/form-data'
			'Content-type: text/xml'
		);

		if ($this->_authProvider != null)
		{
			$this->_authProvider->addHeaders($headers, $url, $method);
		}

		if ($data == null)
		{
			$opts = array('http' =>
				array(
					'method' => $method,
					'user_agent' => $this->_getUserAgent(),
					'header' => /* implode("\r\n", $headers) */$headers,
				)
			);
		}
		else
		{
			//$postdata = http_build_query(array('data' => $data));
			$postdata = $data;

			$opts = array('http' =>
				array(
					'method' => $method,
					'user_agent' => $this->_getUserAgent(),
					'header' => /* implode("\r\n", $headers) */$headers,
					'content' => $postdata
				)
			);
		}

		$context = stream_context_create($opts);

		$this->_stats['requestCount']++;

		if ($this->_options['outputFolder'] != null)
		{
			file_put_contents($this->_getOutputFilename('out', $relativeUrl, $method), $data);
		}

		$result = @file_get_contents($url, false, $context);
		if ($result === false || $result == null)
		{
			$e = error_get_last();
			if (isset($http_response_header))
			{
				$ex = new OSM_HttpException($http_response_header);
			}
			else
			{
				$ex = new OSM_HttpException($e['message']);
			}
				if( $ex->getMessage() != 'HTTP/1.1 200 OK' )
					throw $ex ;
		}

		if ($this->_options['outputFolder'] != null)
		{
			file_put_contents($this->_getOutputFilename('in', $relativeUrl, $method), $result);
		}

		$this->_stats['loadedBytes'] += strlen($result);

		return $result;
	}

	/**
	 * Return the designated object.
	 *
	 * Reuse the loaded one if exists and $full is not set.
	 *
	 * @param type $type
	 * @param type $id
	 * @param boolean $full
	 * @return OSM_Objects_Object
	 */
	public function getObject($type, $id, $full = false) {

		OSM_ZLog::debug(__METHOD__, 'type: ', $type, ', id: ', $id, ', full: ', ($full ? 'true' : 'false'));

		if (!preg_match('/\d+/', $id))
		{
			throw new OSM_Exception('Invalid object Id');
		}

		switch ($type)
		{
			case self::OBJTYPE_RELATION:
				if (!$full && array_key_exists($id, $this->_relations))
					return $this->_relations[$id];
				break;

			case self::OBJTYPE_WAY:
				if (!$full && array_key_exists($id, $this->_ways))
					return $this->_ways[$id];
				break;

			case self::OBJTYPE_NODE:
				if (!$full && array_key_exists($id, $this->_nodes))
					return $this->_nodes[$id];
				break;

			default:
				throw new OSM_Exception('Unknow object type "' . $type . '"');
				break;
		}

		// Query "full" on a "node" will cause a 404 not found
		if ($type == self::OBJTYPE_NODE)
			$full = false;

		$relativeUrl = $type . '/' . $id . ($full ? '/full' : '' );

		$result = $this->_httpApi($relativeUrl, null, 'GET');

		if (OSM_ZLog::isDebug())
			OSM_Zlog::debug(__METHOD__, print_r($result, true));

		//return $this->createObjectsfromXml($type, $result, $full);
		$this->createObjectsfromXml($result);

		switch ($type)
		{
			case self::OBJTYPE_RELATION:
				return $this->_relations[$id];
				break;

			case self::OBJTYPE_WAY:
				return $this->_ways[$id];
				break;

			case self::OBJTYPE_NODE:
				return $this->_nodes[$id];
				break;
		}
	}

	/**
	 * Load or get a node by Id from loaded objects.
	 *
	 * Use removeObject to force the reload of the object.
	 *
	 * @param string $id
	 * @return OSM_Objects_Node
	 */
	public function getNode($id) {
		return $this->getObject(self::OBJTYPE_NODE, $id);
	}

	/**
	 * Load or get a way by Id from loaded objects.
	 *
	 * Use removeObject to force the reload of the object.
	 *
	 * @param string $id
	 * @param bool $full With its nodes (true) or not (false=default)
	 * @return OSM_Objects_Way
	 */
	public function getWay($id, $full = false) {
		return $this->getObject(self::OBJTYPE_WAY, $id, $full);
	}

	/**
	 * Load or get a relation by Id from loaded objects.
	 *
	 * Use removeObject to force the reload of the object.
	 *
	 * @param string $id The relation Id
	 * @param bool $full true for loading all relation's members
	 * @return OSM_Objects_Relation
	 */
	public function getRelation($id, $full = false) {
		return $this->getObject(self::OBJTYPE_RELATION, $id, $full);
	}

	public function loadOSMFile( $osmFilename )
	{
		if( ! file_exists($osmFilename) )
			throw new Exception('File not found "'.$osmFilename.'"');

		$this->createObjectsfromXml( file_get_contents($osmFilename) );
	}

	/**
	 * Create objects and fill objects tables from a OSM xml document (string).
	 *
	 * @param string $xmlStr
	 */
	public function createObjectsfromXml($xmlStr) {

		OSM_ZLog::debug(__METHOD__, $xmlStr);

		if (empty($xmlStr))
		{
			throw new OSM_Exception('Xml string could not be empty');
		}

		$xmlObj = simplexml_load_string($xmlStr);

		if ($xmlObj == null)
		{
			_err('Failed to parse xml: [' . print_r($xmlStr, true) . ']');
			throw new OSM_Exception('Failed to parse xml');
		}

		$this->_loadedXml[] = $xmlStr;

		// Take all others object
		$objects = $xmlObj->xpath('/osm/*');
		foreach ($objects as $obj)
		{
			OSM_ZLog::debug(__METHOD__, 'subobjects type=', $obj->getName());
			switch ($obj->getName())
			{
				case self::OBJTYPE_RELATION :
					$r = OSM_Objects_Relation::fromXmlObj($obj);
					$this->_relations[$r->getId()] = $r;
					break;

				case self::OBJTYPE_WAY :
					$w = OSM_Objects_Way::fromXmlObj($obj);
					$this->_ways[$w->getId()] = $w;
					break;

				case self::OBJTYPE_NODE :
					$n = OSM_Objects_Node::fromXmlObj($obj);
					$this->_nodes[$n->getId()] = $n;
					break;

				case 'note':
				case 'meta':
				case 'remark':
					break;

				default:
					throw new OSM_Exception('Object "' . $obj->getName() . '" is not supported');
			}
		}
	}

	public function hasObject($type, $id) {

		switch ($type)
		{
			case self::OBJTYPE_RELATION:
				if (array_key_exists($id, $this->_relations))
					return true;
				break;

			case self::OBJTYPE_WAY:
				if (array_key_exists($id, $this->_ways))
					return true;
				break;

			case self::OBJTYPE_NODE:
				if (array_key_exists($id, $this->_nodes))
					return true;
				break;

			default:
				throw new OSM_Exception('Unknow object type "' . $type . '"');
		}
		return false;
	}

	public function hasNode($id) {
		return $this->hasObject(self::OBJTYPE_NODE, $id);
	}

	public function hasWay($id) {
		return $this->hasObject(self::OBJTYPE_WAY, $id);
	}

	public function hasRelation($id) {
		return $this->hasObject(self::OBJTYPE_RELATION, $id);
	}

	/**
	 * Returns all loaded objects.
	 * @return OSM_Objects_Object[] List of objects
	 */
	public function &getObjects() {

		$result = array_merge(array_values($this->_relations), array_values($this->_ways), array_values($this->_nodes));
		return $result;
	}

	/**
	 * Returns all loaded relations
	 * @return OSM_Objects_Relation[]
	 */
	public function getRelations() {
		return array_values($this->_relations);
	}

	/**
	 * Returns all loaded ways
	 * @return OSM_Objects_Way[]
	 */
	public function getWays() {
		return array_values($this->_ways);
	}

	/**
	 * Returns all loaded nodes
	 * @return OSM_Objects_Node[]
	 */
	public function getNodes() {
		return array_values($this->_nodes);
	}

	/**
	 * Returns all loaded objects which are matching tags attributes
	 *
	 * @param array $searchTags is a array of Key=>Value.
	 * @return OSM_Objects_Object[]
	 */
	public function &getObjectsByTags(array $searchTags) {

		$results = array_merge(
			$this->getRelationsByTags($searchTags), $this->getWaysByTags($searchTags), $this->getNodesByTags($searchTags)
		);
		return $results;
	}

	/**
	 * Returns all loaded nodes which are matching tags attributes
	 *
	 * @param array $tags is a array of Key=>Value.
	 * @return OSM_Objects_Node[]
	 */
	public function &getRelationsByTags(array $searchTags) {

		$results = array();
		foreach ($this->_relations as $obj)
		{
			if ($obj->hasTags($searchTags))
				$results[] = $obj;
		}
		return $results;
	}

	/**
	 * Returns all loaded nodes which are matching tags attributes
	 *
	 * @param array $tags is a array of Key=>Value.
	 * @return OSM_Objects_Node[]
	 */
	public function &getWaysByTags(array $searchTags) {

		$results = array();
		foreach ($this->_ways as $obj)
		{
			if ($obj->hasTags($searchTags))
				$results[] = $obj;
		}
		return $results;
	}

	/**
	 * Returns all loaded nodes which are matching tags attributes
	 *
	 * @param array $tags is a array of Key=>Value.
	 * @return OSM_Objects_Node[]
	 */
	public function &getNodesByTags(array $searchTags) {

		$results = array();
		foreach ($this->_nodes as $obj)
		{
			if ($obj->hasTags($searchTags))
				$results[] = $obj;
		}
		return $results;
	}

	/**
	 * Removes an object loaded by the API.
	 * No exception is raised if the object is unknown.
	 * @param string $type
	 * @param int $id
	 */
	public function removeObject($type, $id) {

		switch ($type)
		{
			case self::OBJTYPE_RELATION:
				if (array_key_exists($id, $this->_relations))
					unset($this->_relations[$id]);
				break;

			case self::OBJTYPE_WAY:
				if (array_key_exists($id, $this->_ways))
					unset($this->_ways[$id]);
				break;

			case self::OBJTYPE_NODE:
				if (array_key_exists($id, $this->_nodes))
					unset($this->_nodes[$id]);
				break;

			default:
				throw new OSM_Exception('Unknow object type "' . $type . '"');
				break;
		}
	}

	public function removeAllObjects() {
		$this->_relations = $this->_ways = $this->_nodes = array();
	}

	/**
	 * Reload a given OSM Object into the objects collection.
	 *
	 * It remove the object before.
	 *
	 * @param string $type
	 * @param int $id
	 * @param bool $full
	 * @return OSM_Objects_Object the reverted object
	 */
	public function reloadObject($type, $id, $full = false) {

		$this->removeObject($type, $id);
		return $this->getObject($type, $id, $full);
	}

	/**
	 * Retreive objects with the Overpass-Api and fill objects collection from result.
	 *
	 * If you do not need to save back data and want efficient network communication you can
	 * swith $withMeta to false to avoid download of metadata.
	 *
	 * @param string $xmlQuery
	 * @param string $withMeta To get metadata which are needed for saving back data (Version, User...).
	 */
	public function queryOApi($xmlQuery, $withMeta = true) {

		if ($withMeta)
		{
			$this->_oapiAddMetadata($xmlQuery);
		}

		OSM_ZLog::notice(__METHOD__, ' url: ', $this->_options['oapi_url']);
		OSM_ZLog::debug(__METHOD__, $xmlQuery);

		$postdata = http_build_query(array('data' => $xmlQuery));

		$opts = array('http' =>
			array(
				'method' => 'POST',
				'user_agent' => $this->_getUserAgent(),
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			)
		);
		$context = stream_context_create($opts);

		$this->_stats['requestCount']++;

		$result = @file_get_contents($this->_options['oapi_url'], false, $context);
		if ($result === false)
		{
			$e = error_get_last();
			if (isset($http_response_header))
			{
				throw new OSM_HttpException($http_response_header);
			}
			else
			{
				throw new OSM_HttpException($e['message']);
			}
		}

		$this->_stats['loadedBytes'] += strlen($result);

		$this->createObjectsfromXml($result);
	}

	/**
	 *
	 * @param string $xmlQuery
	 */
	protected function _oapiAddMetadata(&$xmlQuery) {

		OSM_ZLog::debug(__METHOD__);

		$x = new SimpleXMLElement($xmlQuery);
		$xPrints = $x->xpath('//print');
		foreach ($xPrints as $xPrint)
		{
			if ($xPrint['mode'] == null)
			{
				$xPrint->addAttribute('mode', 'meta');
			}
		}
		$xmlQuery = $x->asXml();
	}

	/**
	 * Create and add a new node to the objects collection.
	 *
	 * @param type $lat
	 * @param type $lon
	 * @param array $tags
	 * @return OSM_Objects_Node
	 */
	public function addNewNode($lat = 0, $lon = 0, array $tags = null) {

		$node = new OSM_Objects_Node($this->_newIdCounter--, $lat, $lon, $tags);
		$this->_nodes[$node->getId()] = $node;
		return $node;
	}

	/**
	 * Create and add a new way to the objects collection.
	 *
	 * @param array $nodes
	 * @param array $tags
	 * @return OSM_Objects_Way
	 */
	public function addNewWay(array $nodes = null, array $tags = null) {

		$way = new OSM_Objects_Way($this->_newIdCounter--);
		if (is_array($nodes))
			$way->addNodes($nodes);
		if (is_array($tags))
			$way->addTags($tags);
		return $way;
	}

	/**
	 * Create and add a new relation to the objects collection.
	 *
	 * @param array $members
	 * @param array $tags
	 * @return OSM_Objects_Relation
	 */
	public function addNewRelation(array $members = null, array $tags = null) {

		$relation = new OSM_Objects_Relation($this->_newIdCounter--);
		if (is_array($members))
			$relation->addMembers($members);
		if (is_array($tags))
			$relation->addTags($tags);
		return $relation;
	}

	public function &getDirtyObjects() {

		$dirtyObjects = array();

		// union of objects
		$objects = $this->_relations + $this->_ways + $this->_nodes;

		foreach ($objects as $obj)
		{
			OSM_ZLog::debug(__METHOD__, 'Is Object "' . get_class($obj) . '" "' . $obj->getId() . '" dirty');
			if ($obj->isDirty())
			{
				OSM_ZLog::info(__METHOD__, 'Object "' . $obj->getId() . '" is dirty');
				$dirtyObjects[] = $obj;
			}
		}

		return $dirtyObjects;
	}

	/**
	 * Return Xml document of all contained objects.
	 * @param bool $onlyDirtyObjects To get only modified objects (added, modified or deleted)
	 * @return string Xml document as a string.
	 */
	public function getXmlDocument($onlyDirtyObjects = false) {
		$xml = '<osm version="0.6" upload="true" generator="' . $this->_getUserAgent() . '">' . "\n";
		// union of objects
		$objects = $this->_relations + $this->_ways + $this->_nodes;
		foreach ($objects as $obj)
		{
			if ($onlyDirtyObjects)
			{
				if ($obj->isDirty())
				{
					$xml .= $obj->asXmlStr() . "\n";
				}
			}
			else
			{
				$xml .= $obj->asXmlStr() . "\n";
			}
		}
		$xml .= '</osm>' . "\n";
		return $xml;
	}

	/**
	 * Save changes made to objects.
	 *
	 * Objects stay dirties after save. You have to destroy/reload them to get them up-to-date (id, version, ...)
	 *
	 * @param type $comment
	 * @return bool true if has saved something, false if nothing to save.
	 * @throws OSM_Exception if not authenticated
	 */
	public function saveChanges($comment) {

		OSM_ZLog::notice(__METHOD__, 'comment = "', $comment, '"');

		if ($this->_authProvider == null)
		{
			throw new OSM_Exception('Must be authenticated');
		}

		if ($this->_options['simulation'])
		{
			OSM_ZLog::notice(__METHOD__, 'Simulation Mode, not saving' . ($this->_options['outputFolder'] != null ? ' but look inside folder ' . $this->_options['outputFolder'] : ''));
		}

		$dirtyObjects = $this->getDirtyObjects();
		$dirtyObjectsCount = count($dirtyObjects);

		if ($dirtyObjectsCount == 0)
		{
			OSM_ZLog::notice(__METHOD__, 'No dirty object, abort save');
			return false;
		}
		OSM_ZLog::notice(__METHOD__, 'Has dirty ', $dirtyObjectsCount, ' objects');

		$changeSet = $this->_createChangeSet($comment);

		$changeSetId = $changeSet->getId();

		foreach ($dirtyObjects as $obj)
		{
			//OSM_ZLog::debug(__METHOD__,print_r($obj,true));

			if ($obj->isDeleted())
			{
				$changeSet->deleteObject($obj);
			}
			else
			{
				$changeSet->addObject($obj);
			}
		}

		$this->_uploadChangeSet($changeSet);

		$this->_closeChangeSet($changeSet);

		return true;
	}

	/**
	 *
	 * @param string $comment
	 * @return OSM_Objects_ChangeSet
	 */
	protected function _createChangeSet($comment) {

		$relativeUrl = 'changeset/create';

		if ($this->_options['simulation'])
		{
			OSM_ZLog::info(__METHOD__, 'Simulation Mode, set changeset id to 999');
			$result = 999;
		}
		else
		{
			$result = $this->_httpApi($relativeUrl, OSM_Objects_ChangeSet::getCreateXmlStr($comment, $this->_getUserAgent()), 'PUT');
		}

		OSM_ZLog::debug(__METHOD__, var_export($result, true));

		$changeSet = new OSM_Objects_ChangeSet($result);
		return $changeSet;
	}

	protected function _closeChangeSet($changeSet) {

		$relativeUrl = 'changeset/' . $changeSet->getId() . '/close';

		if ($this->_options['simulation'])
		{
			
		}
		else
		{
			$result = $this->_httpApi($relativeUrl, null, 'PUT');
		}
	}

	protected function _uploadChangeSet($changeSet) {

		$relativeUrl = 'changeset/' . $changeSet->getId() . '/upload';

		$xmlStr = $changeSet->getUploadXmlStr($this->_getUserAgent());

		if (OSM_ZLog::isDebug())
			file_put_contents('debug.OSM_Api._uploadChangeSet.postdata.xml', $xmlStr);

		if ($this->_options['simulation'])
		{
			$result = 'Simulation, no call to Api';
		}
		else
		{
			$result = $this->_httpApi($relativeUrl, $xmlStr, 'POST');
		}

		OSM_ZLog::debug(__METHOD__, print_r($result, true));
	}

	/**
	 * Tell if the Node is inside the polygon defined by the Relation.
	 * Note: Does not works with multipolygon (outer/inner/...)
	 *
	 * @param OSM_Objects_Node $node2test
	 * @param OSM_Objects_Relation $relation
	 * @return bool
	 */
	public function isNodeInsideRelationPolygon(OSM_Objects_Node $node2test, OSM_Objects_Relation $relation) {

		$poly1 = $this->getPolygon($relation);

		$poly2 = new \OSM\Tools\Polygon();
		$poly2->addv($node2test->getLat(), $node2test->getLon());

		return $poly1->isPolyInside($poly2);
	}

	/**
	 *
	 * @param OSM_Objects_Relation $relation
	 * @return \OSM\Tools\Polygon
	 */
	public function getPolygon(OSM_Objects_Relation $relation) {

		$poly = new \OSM\Tools\Polygon();

		$ways = $this->getRelationWaysOrdered($relation);
		$waysCount = count($ways);


		$lastviewNodeId = 0;
		if ($ways[0]->getFirstNodeRef() == $ways[$waysCount - 1]->getFirstNodeRef() || $ways[0]->getFirstNodeRef() == $ways[$waysCount - 1]->getLastNodeRef())
		{
			$lastviewNodeId = $ways[0]->getFirstNodeRef();
		}
		else
		{
			$lastviewNodeId = $ways[0]->getLastNodeRef();
		}

		for ($wi = 0; $wi < $waysCount; $wi++)
		{
			$way = $ways[$wi];
			$nodeRefs = $way->getNodesRefs();
			if ($lastviewNodeId == $way->getFirstNodeRef())
			{
				// à l'endroit
				//echo 'draw '.$way->getId() .' -> '."\n";
				for ($i = 0; $i < count($nodeRefs); $i++)
				{
					$nodeRef = $nodeRefs[$i];
					$node = $this->_nodes[$nodeRef];
					$poly->addv($node->getLat(), $node->getLon());
				}
			}
			else
			{
				// à l'envers
				//echo 'draw '.$way->getId() .' <- '."\n";
				for ($i = count($nodeRefs) - 1; $i >= 0; $i--)
				{
					$nodeRef = $nodeRefs[$i];
					$node = $this->_nodes[$nodeRef];
					$poly->addv($node->getLat(), $node->getLon());
				}
			}

			$toto = ($wi + 1) % $waysCount;
			if ($nodeRef == $ways[$toto]->getLastNodeRef())
			{
				$lastviewNodeId = $ways[$toto]->getLastNodeRef();
			}
			else
			{
				$lastviewNodeId = $ways[$toto]->getFirstNodeRef();
			}
		}
		return $poly;
	}

	/**
	 * Return relation's way ordered by nodes position, like we can draw a well formed polygon.
	 *
	 * @param OSM_Objects_Relation $relation
	 * @return OSM_Objects_Way[]
	 */
	public function getRelationWaysOrdered(OSM_Objects_Relation $relation) {

		$membersWays = $relation->findMembersByType(self::OBJTYPE_WAY);

		$w1 = $membersWays[0];
		if (!array_key_exists($w1->getRef(), $this->_ways))
		{
			throw new OSM_Exception('Way not loaded, you must load the full relation.');
		}

		$waysOrderedIds = array();
		$waysOrdered = array();

		$ww1 = $this->_ways[$w1->getRef()];
		$waysOrderedIds[$ww1->getId()] = $ww1;
		$waysOrdered[] = $ww1;
		for ($i = 0; $i < count($membersWays); $i++)
		{
			if (!array_key_exists($w1->getRef(), $this->_ways))
			{
				throw new OSM_Exception('Way not loaded, you must load the full relation.');
			}
			$ww1 = $this->_ways[$w1->getRef()];
			for ($j = 0; $j < count($membersWays); $j++)
			{
				$w2 = $membersWays[$j];
				if ($w1->getRef() == $w2->getRef())
					continue;
				if (array_key_exists($w2->getRef(), $waysOrderedIds))
				{
					continue;
				}

				if (!array_key_exists($w2->getRef(), $this->_ways))
				{
					throw new OSM_Exception('Way not loaded, you must load the full relation.');
				}
				$ww2 = $this->_ways[$w2->getRef()];

				$nId1F = $ww1->getFirstNodeRef();
				$nId1L = $ww1->getLastNodeRef();
				$nId2F = $ww2->getFirstNodeRef();
				$nId2L = $ww2->getLastNodeRef();

				if ($nId1F == $nId2F || $nId1F == $nId2L || $nId1L == $nId2F || $nId1L == $nId2L)
				{
					$waysOrderedIds[$ww2->getId()] = $ww2;
					$waysOrdered[] = $ww2;
					$w1 = $w2;
					break;
				}
			}
		}

		return $waysOrdered;
	}

	/**
	 * Return all way's nodes coordinates
	 * @return array
	 */
	public function &getWayNodesCoordinates( OSM_Objects_Way $way )
	{
		$coords = array();
		$nodesRef = $way->getNodesRefs();
		$n = count( $nodesRef );
		for( $i=0; $i<$n; $i++ )
		{
			$node = $this->getNode( $nodesRef[$i] );
			$coords[] = array( $node->getLat(), $node->getLon() );
		}
		return $coords ;
	}

	public function getStatsRequestCount() {
		return $this->_stats['requestCount'];
	}

	public function getStatsLoadedBytes() {
		return $this->_stats['loadedBytes'];
	}

	/**
	 * Return a string like "MyApp / Yapafo 0.1", based on the "appName" options and the library constants.
	 * This appears as the editor's name in the changeset properties (key "crated_by")
	 * @return string user agent string
	 */
	protected function _getUserAgent() {
		$userAgent = "";
		if ($this->_options['appName'] != "")
		{
			$userAgent .= $this->_options['appName'] . ' / ';
		}
		$userAgent .= self::USER_AGENT . ' v' . self::VERSION;
		return $userAgent;
	}

	protected function _getOutputFilename($inOrOut, $relativeUrl, $method) {

		// file_put_contents($this->_getOutputFilename('in', $relativeUrl, $method, $data))

		return $this->_options['outputFolder'] .
			DIRECTORY_SEPARATOR . __CLASS__
			. '_' . sprintf('%04d', ++$this->_outputWriteCount) . '-' . time()
			. '_' . $inOrOut . '-' . $method . '-' . urlencode($relativeUrl) . '.txt';
	}

	/**
	 * After an authorization, client should call this method to clear permissions cache. 
	 * @todo It's not nice to leave the client with that job. The Api should manage this case itself... But I do not find any idea...
	 */
	public function clearCachedAuthPermissions()
	{
		$this->_cachedPermissions = null ;
	}

	/**
	 * Implements GET /api/0.6/permissions
	 * 
	 * https://github.com/openstreetmap/openstreetmap-website/pull/45
	 * 
	 * @param bool $force
	 */
	public function getAuthPermissions($force = false) {

		if (!$force && $this->_cachedPermissions != null)
		{
			return $this->_cachedPermissions;
		}

		$result = $this->_httpApi('/permissions');
		OSM_ZLog::debug(__METHOD__, $result);

		/*
		  <?xml version="1.0" encoding="UTF-8"?>
		  <osm generator="OpenStreetMap Server" version="0.6">
		  <permissions>
		  <permission name="allow_read_prefs"/>
		  <permission name="allow_write_prefs"/>
		  <permission name="allow_write_api"/>
		  <permission name="allow_read_gpx"/>
		  </permissions>
		  </osm>
		 */

		$x = new SimpleXMLElement($result);
		$perms = $x->xpath('/osm/permissions/permission');

		$this->_cachedPermissions = array();
		foreach ($perms as $perm)
		{
			$this->_cachedPermissions[] = (string) $perm['name'];
		}

		/*
		  protected $_cachedPermissions = array(
		  'allow_read_prefs', // read user preferences
		  'allow_write_prefs', // modify user preferences
		  'allow_write_diary', // create diary entries, comments and make friends
		  'allow_write_api', // modify the map
		  'allow_read_gpx', // allow_read_gpx
		  'allow_write_gpx'	 // upload GPS traces
		  );
		 */
		return $this->_cachedPermissions;
	}

	/**
	 * @return bool allow_read_prefs
	 */
	public function isAllowedToReadPrefs() {
		return in_array('allow_read_prefs', $this->getAuthPermissions());
	}

	/**
	 * @return bool allow_write_prefs
	 */
	public function isAllowedToWritePrefs() {
		return in_array('allow_write_prefs', $this->getAuthPermissions());
	}

	/**
	 * @return bool allow_write_diary
	 */
	public function isAllowedToWriteDiary() {
		return in_array('allow_write_diary', $this->getAuthPermissions());
	}

	/**
	 * @return bool allow_write_api
	 */
	public function isAllowedToWriteApi() {
		return in_array('allow_write_api', $this->getAuthPermissions());
	}

	/**
	 * @return bool allow_read_gpx
	 */
	public function isAllowedToReadGpx() {
		return in_array('allow_read_gpx', $this->getAuthPermissions());
	}

	/**
	 * @return bool allow_write_gpx
	 */
	public function isAllowedToWriteGpx() {
		return in_array('allow_write_gpx', $this->getAuthPermissions());
	}

	/**
	 *
	 * @return OSM_Objects_UserDetails
	 * @throws OSM_Exception if not authenticated
	 */
	public function getUserDetails() {

		if ($this->_authProvider == null)
		{
			throw new OSM_Exception('Must be authenticated');
		}

		$result = $this->_httpApi('/user/details');

		OSM_ZLog::debug(__METHOD__, $result);

		return OSM_Objects_UserDetails::createFromXmlString($result);
	}

	/**
	 * Retreive all user preferences from openstreetmap.org website.
	 *
	 * @return array
	 */
	public function &getUserPreferences() {

		if ($this->_authProvider == null)
		{
			throw new OSM_Exception('Must be authenticated');
		}

		$result = $this->_httpApi('/user/preferences');

		OSM_ZLog::debug(__METHOD__, $result);

		$prefs = array();

		$x = new SimpleXMLElement($result);
		foreach ($x->preferences->children() as $p)
		{
			$prefs[(string) $p['k']] = (string) $p['v'];
		}

		return $prefs;
	}

	/**
	 * Set a user preference value.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function setUserPreference($key, $value) {

		if ($this->_authProvider == null)
		{
			throw new OSM_Exception('Must be authenticated');
		}

		$result = $this->_httpApi(
			'/user/preferences/' . rawurlencode(utf8_encode($key)), rawurlencode(utf8_encode($value)), 'PUT');

		OSM_ZLog::debug(__METHOD__, $result);
	}

}
