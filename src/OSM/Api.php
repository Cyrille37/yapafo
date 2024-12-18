<?php

namespace Cyrille37\OSM\Yapafo;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception;
use Cyrille37\OSM\Yapafo\Exceptions\HttpException;
use Cyrille37\OSM\Yapafo\Objects\ChangeSet;
use Cyrille37\OSM\Yapafo\Objects\Node;
use Cyrille37\OSM\Yapafo\Objects\OSM_Object;
use Cyrille37\OSM\Yapafo\Objects\Relation;
use Cyrille37\OSM\Yapafo\Objects\UserDetails;
use Cyrille37\OSM\Yapafo\Objects\Way;
use Cyrille37\OSM\Yapafo\Tools\Polygon;
use Cyrille37\OSM\Yapafo\Tools\Config;
use Cyrille37\OSM\Yapafo\Tools\Logger;
use Psr\Log\LogLevel;

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
class OSM_Api
{

	const VERSION = '2.0';
	const USER_AGENT = 'https://github.com/Cyrille37/yapafo';

	//const URL_DEV_UK = 'https://master.apis.dev.openstreetmap.org/api/0.6';
	/**
	 * doc: https://wiki.openstreetmap.org/wiki/Sandbox_for_editing
	 * alias https://master.apis.dev.openstreetmap.org
	 */
	const URL_DEV_UK = 'https://api06.dev.openstreetmap.org';
	//deprecated: const OSMAPI_URL_PROD_PROXY_LETTUFE = 'http://beta.letuffe.org/api/0.6';
	//const URL_PROD_FR = 'http://api.openstreetmap.fr/api/0.6';
	const URL_PROD_UK = 'https://api.openstreetmap.org';

	const URL_PATH_API = '/api/0.6';

	/**
	 * Instances: https://wiki.openstreetmap.org/wiki/Overpass_API#Public_Overpass_API_instances
	 * FR: parse error: Unknown type "nwr"
	 */
	//const OAPI_URL_FR = 'https://overpass.openstreetmap.fr/api/interpreter';
	const OAPI_URL_RU = 'https://overpass.openstreetmap.ru/api/interpreter';
	//const OAPI_URL_LETUFFE = 'http://overpassapi.letuffe.org/api/interpreter';
	const OAPI_URL_DE = 'https://overpass-api.de/api/interpreter';
	const OAPI_URL_CH = 'https://overpass.osm.ch/api/interpreter';

	/**
	 * http://www.overpass-api.de/api/xapi
	 * http://api.openstreetmap.fr/xapi
	 * deprecated: http://overpassapi.letuffe.org/api/xapi
	 */
	const XAPI_URL_DE = 'http://www.overpass-api.de/api/xapi';
	//const XAPI_URL_FR = 'http://api.openstreetmap.fr/xapi';
	//const XAPI_URL_LETTUFE = 'http://overpassapi.letuffe.org/api/xapi';

	const OAUTH2_NO_REDIRECT_URL = 'urn:ietf:wg:oauth:2.0:oob';

	// List of permissions/scopes : https://wiki.openstreetmap.org/wiki/API_v0.6#List_of_permissions

	const PERMS_READ_PREFS = 'allow_read_prefs';
	const PERMS_WRITE_PREFS = 'allow_write_prefs';
	const PERMS_WRITE_DIARY = 'allow_write_diary';
	const PERMS_WRITE_API = 'allow_write_api';
	const PERMS_READ_GPX = 'allow_read_gpx';
	const PERMS_WRITE_GPX = 'allow_write_gpx';
	const PERMS_WRITE_NOTE = 'allow_write_notes';
	const PERMS_WRITE_REDACTIONS = 'allow_write_redactions';
	const PERMS_OPENID = 'allow_openid';

	const SCOPES4HUMANS = [
		'1' => ['value' => 'read_prefs', 'desc' => 'Read user preferences'],
		'2' => ['value' => 'write_prefs', 'desc' => 'Write user preferences'],
		'3' => ['value' => 'write_diary', 'desc' => 'Create diary entries, comments and make friends'],
		'4' => ['value' => 'write_api', 'desc' => 'Modify the map'],
		'5' => ['value' => 'read_gpx', 'desc' => 'Read private GPS traces'],
		'6' => ['value' => 'write_gpx', 'desc' => 'Upload GPS traces'],
		'7' => ['value' => 'write_notes', 'desc' => 'Modify notes'],
	];

	/**
	 */
	protected $_options = [
		// simulation is set by default to avoid (protected against) unwanted write !
		'simulation' => true,
		'url' => OSM_Api::URL_DEV_UK,
		'url4Write' =>  null,
		'oapi_url' => OSM_Api::OAPI_URL_DE,
		'xapi_url' => OSM_Api::XAPI_URL_DE,
		// to store every network communications (load/save) in a file.
		'outputFolder' => null,
		// to store every network result in file, to avoid overloading services.
		'cacheFolder' => null,
		'appName' => '', // name for the application using the API
		'log' => [
			'logger' => null,
			'level' => LogLevel::NOTICE
		],
	];
	protected $_stats = array(
		'requestCount' => 0,
		'loadedBytes' => 0
	);
	protected $_url;
	protected $_url4Write;

	protected $_osm_access_token;

	protected $_relations = [];
	protected $_ways = [];
	protected $_nodes = [];
	protected $_newIdCounter = -1;

	/**
	 * Store all xml Objects
	 * @var \SimpleXMLElement
	 */
	protected $_loadedXml = array();

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger;

	protected $cacheDisabled = false;

	public function __construct($options = [])
	{
		// Retrieve the OAuth Access Token, from Config or $options
		$this->_osm_access_token = Config::get('osm_access_token');
		if (array_key_exists('access_token', $options)) {
			$this->_osm_access_token = $options['access_token'];
			unset($options['access_token']);
		}

		$this->_options['simulation'] = Config::get('osm_api_simulation', $this->_options['simulation']);
		$this->_options['url'] = Config::get('osm_api_url', $this->_options['url']) . OSM_Api::URL_PATH_API;
		$this->_options['url4Write'] = Config::get('osm_api_url_4write', $this->_options['url4Write']);
		if (empty($this->_options['url4Write']))
			$this->_options['url4Write'] = $this->_options['url'];
		$this->_options['oapi_url'] = Config::get('oapi_url', $this->_options['oapi_url']);
		$this->_options['xapi_url'] = Config::get('xapi_url', $this->_options['xapi_url']);
		$this->_options['log']['level'] = Config::get('osm_log_level', $this->_options['log']['level']);
		$this->_options['outputFolder'] = Config::get('osm_api_outputFolder', $this->_options['outputFolder']);
		$this->_options['cacheFolder'] = Config::get('osm_api_cacheFolder', $this->_options['cacheFolder']);

		// Check that all options exist then override defaults
		foreach ($options as $k => $v) {
			if (!array_key_exists($k, $this->_options))
				throw new OSM_Exception('Unknow option "' . $k . '"');
			$this->_options[$k] = $v;
		}

		if (isset($this->_options['log']['logger'])) {
			$this->_logger = $this->_options['log']['logger'];
		} else {
			$this->_logger = Logger::getInstance($this->_options['log']['level']);
		}

		$this->getLogger()->debug('{method} {options}', ['method' => __METHOD__, 'options' => $this->_options]);

		if (!empty($this->_options['outputFolder'])) {
			if (!is_writable($this->_options['outputFolder'])) {
				throw new OSM_Exception('Option "outputFolder" is set but the folder "' . $this->_options['outputFolder'] . '" is not writable.');
			}
		}
		if (!empty($this->_options['cacheFolder'])) {
			if (!is_writable($this->_options['cacheFolder'])) {
				throw new OSM_Exception('Option "cacheFolder" is set but the folder "' . $this->_options['cacheFolder'] . '" is not writable.');
			}
		}
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	public function getLogger()
	{
		return $this->_logger;
	}

	public function isDebug()
	{
		return ($this->_options['log']['level'] == LogLevel::DEBUG);
	}

	public function isCaching()
	{
		if ($this->cacheDisabled)
			return false;
		return ! empty($this->_options['cacheFolder']);
	}

	/**
	 * When Cache is activated, this permits to temporary disable caching.
	 * @param bool $disable 
	 * @return void 
	 */
	public function disableCache($disable = true)
	{
		$this->cacheDisabled = $disable;
	}

	public function isSimulation()
	{
		return (isset($this->_options['simulation']) && $this->_options['simulation']);
	}

	public function setAccesToken($accessToken)
	{
		$this->_osm_access_token = $accessToken;
	}

	public function isAuthenticated()
	{
		return $this->_osm_access_token ? true : false;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getOption($key)
	{
		if (!array_key_exists($key, $this->_options))
			return null;
		return $this->_options[$key];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return OSM_Api fluent interface
	 */
	public function setOption($key, $value)
	{
		if (!array_key_exists($key, $this->_options))
			throw new OSM_Exception('Unknow Api option "' . $key . '"');
		$this->_options[$key] = $key;
		return $this;
	}

	/**
	 * @return \SimpleXMLElement
	 */
	public function getLastLoadedXmlObject()
	{
		return simplexml_load_string($this->_loadedXml[count($this->_loadedXml) - 1]);
	}

	/**
	 *
	 * @return string
	 */
	public function getLastLoadedXmlString()
	{
		return $this->_loadedXml[count($this->_loadedXml) - 1];
	}

	protected function _httpApi($relativeUrl, $data = null, $method = 'GET')
	{
		$url = null;
		switch ($method) {
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
		$url .= $relativeUrl;

		$this->getLogger()->notice('{method} {http_method} {url}', ['method' => __METHOD__, 'http_method' => $method, 'url' => $url]);

		$headers = array(
			// Failed with PUT :
			//'Content-type: application/x-www-form-urlencoded'
			// Works with PUT :
			//'Content-type: multipart/form-data'
			'Content-type: text/xml'
		);

		if ($this->_osm_access_token) {
			$headers[] = 'Authorization: Bearer ' . $this->_osm_access_token;
		}

		$opts = [
			'http' => [
				'method' => $method,
				'user_agent' => $this->_getUserAgent(),
				'header' => /* implode("\r\n", $headers) */ $headers,
			]
		];
		if ($data != null) {
			//$postdata = http_build_query(array('data' => $data));
			$postdata = $data;
			$opts['http']['content'] = $postdata;
		}

		$this->getLogger()->debug(__METHOD__ . ' opts:{opts}', ['opts' => $opts]);

		$this->_stats['requestCount']++;

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('out', $relativeUrl, $method), $data);
		}

		$cacheFile = null;
		$result = null;

		if ($this->_options['cacheFolder'] != null && (!$this->cacheDisabled)) {
			$cacheFile = $this->_getCacheFilename($relativeUrl, $method, $data);
			if (file_exists($cacheFile)) {
				$this->getLogger()->notice('Read from cache {method} {http_method} {url}', ['method' => __METHOD__, 'http_method' => $method, 'url' => $relativeUrl]);
				$result = @file_get_contents($cacheFile);
			}
		}

		if (! $result) {
			$context = stream_context_create($opts);
			$result = @file_get_contents($url, false, $context);
			if ($cacheFile) {
				file_put_contents($cacheFile, $result);
			}
		}

		if ($result === false || $result == null) {
			$e = error_get_last();
			if (isset($http_response_header)) {
				$ex = new HttpException($http_response_header);
			} else {
				$ex = new HttpException($e['message']);
			}
			if ($ex->getMessage() != 'HTTP/1.1 200 OK')
				throw $ex;
		}

		if ($this->_options['outputFolder'] != null) {
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
	 * @param string $type
	 * @param string $id
	 * @param boolean $full
	 * @return Object
	 */
	public function getObject($type, $id, $full = false)
	{

		$this->getLogger()->debug('{method} type:{type} id:{id} full:{full}', ['method' => __METHOD__, 'type' => $type, 'id' => $id, 'full' => $full]);

		if (!preg_match('/\d+/', $id)) {
			throw new OSM_Exception('Invalid object Id');
		}

		switch ($type) {
			case OSM_Object::OBJTYPE_RELATION:
				if (!$full && array_key_exists($id, $this->_relations))
					return $this->_relations[$id];
				break;

			case OSM_Object::OBJTYPE_WAY:
				if (!$full && array_key_exists($id, $this->_ways))
					return $this->_ways[$id];
				break;

			case OSM_Object::OBJTYPE_NODE:
				if (!$full && array_key_exists($id, $this->_nodes))
					return $this->_nodes[$id];
				break;

			default:
				throw new OSM_Exception('Unknow object type "' . $type . '"');
				break;
		}

		// Query "full" on a "node" will cause a 404 not found
		if ($type == OSM_Object::OBJTYPE_NODE)
			$full = false;

		$relativeUrl = '/' . $type . '/' . $id . ($full ? '/full' : '');

		$result = $this->_httpApi($relativeUrl, null, 'GET');

		$this->getLogger()->debug('{_m} {result}', ['_m' => __METHOD__, 'result' => $result]);

		//return $this->createObjectsfromXml($type, $result, $full);
		$this->createObjectsfromXml($result);

		switch ($type) {
			case OSM_Object::OBJTYPE_RELATION:
				return $this->_relations[$id];
				break;

			case OSM_Object::OBJTYPE_WAY:
				return $this->_ways[$id];
				break;

			case OSM_Object::OBJTYPE_NODE:
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
	 * @return Node
	 */
	public function getNode($id)
	{
		return $this->getObject(OSM_Object::OBJTYPE_NODE, $id);
	}

	/**
	 * Load or get a way by Id from loaded objects.
	 *
	 * Use removeObject to force the reload of the object.
	 *
	 * @param string $id
	 * @param bool $full With its nodes (true) or not (false=default)
	 * @return Way
	 */
	public function getWay($id, $full = false)
	{
		return $this->getObject(OSM_Object::OBJTYPE_WAY, $id, $full);
	}

	/**
	 * Load or get a relation by Id from loaded objects.
	 *
	 * Use removeObject to force the reload of the object.
	 *
	 * @param string $id The relation Id
	 * @param bool $full true for loading all relation's members
	 * @return Relation
	 */
	public function getRelation($id, $full = false)
	{
		return $this->getObject(OSM_Object::OBJTYPE_RELATION, $id, $full);
	}

	public function loadOSMFile($osmFilename)
	{
		if (!file_exists($osmFilename))
			throw new \Exception('File not found "' . $osmFilename . '"');

		$this->createObjectsfromXml(file_get_contents($osmFilename));
	}

	/**
	 * Create objects and fill objects tables from a OSM xml document (string).
	 *
	 * @param string $xmlStr
	 */
	public function createObjectsfromXml($xmlStr)
	{
		$this->getLogger()->debug('{_m} {xml}', ['_m' => __METHOD__, 'xml' => $xmlStr]);

		if (empty($xmlStr)) {
			throw new OSM_Exception('Xml string could not be empty');
		}

		$xmlObj = simplexml_load_string($xmlStr);

		if ($xmlObj == null) {
			$this->getLogger()->error('{_m} Failed to parse xml {xml}', ['_m' => __METHOD__, 'xml' => $xmlStr]);
			throw new OSM_Exception('Failed to parse xml');
		}

		$this->_loadedXml[] = $xmlStr;

		// Take all others object
		/**
		 * @var \SimpleXMLElement $obj
		 */
		$objects = $xmlObj->xpath('/osm/*');
		foreach ($objects as $obj) {
			$this->getLogger()->debug('{_m} type:{type}', ['_m' => __METHOD__, 'type' => $obj->getName()]);
			switch ($obj->getName()) {
				case OSM_Object::OBJTYPE_RELATION:
					$r = Relation::fromXmlObj($obj);
					$this->_relations[$r->getId()] = $r;
					break;

				case OSM_Object::OBJTYPE_WAY:
					$w = Way::fromXmlObj($obj);
					$this->_ways[$w->getId()] = $w;
					break;

				case OSM_Object::OBJTYPE_NODE:
					$n = Node::fromXmlObj($obj);
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

	public function hasObject($type, $id)
	{

		switch ($type) {
			case OSM_Object::OBJTYPE_RELATION:
				if (array_key_exists($id, $this->_relations))
					return true;
				break;

			case OSM_Object::OBJTYPE_WAY:
				if (array_key_exists($id, $this->_ways))
					return true;
				break;

			case OSM_Object::OBJTYPE_NODE:
				if (array_key_exists($id, $this->_nodes))
					return true;
				break;

			default:
				throw new OSM_Exception('Unknow object type "' . $type . '"');
		}
		return false;
	}

	public function hasNode($id)
	{
		return $this->hasObject(OSM_Object::OBJTYPE_NODE, $id);
	}

	public function hasWay($id)
	{
		return $this->hasObject(OSM_Object::OBJTYPE_WAY, $id);
	}

	public function hasRelation($id)
	{
		return $this->hasObject(OSM_Object::OBJTYPE_RELATION, $id);
	}

	/**
	 * Returns all loaded objects.
	 * @return OSM_Object[] List of objects
	 */
	public function &getObjects()
	{

		$result = array_merge(array_values($this->_relations), array_values($this->_ways), array_values($this->_nodes));
		return $result;
	}

	/**
	 * Returns all loaded relations
	 * @return Relation[]
	 */
	public function getRelations()
	{
		return array_values($this->_relations);
	}

	/**
	 * Returns all loaded ways
	 * @return Way[]
	 */
	public function getWays()
	{
		return array_values($this->_ways);
	}

	/**
	 * Returns all loaded nodes
	 * @return Node[]
	 */
	public function getNodes()
	{
		return array_values($this->_nodes);
	}

	/**
	 * Returns all loaded objects which are matching tags attributes
	 *
	 * @param array $searchTags is a array of Key=>Value.
	 * @return Object[]
	 */
	public function &getObjectsByTags(array $searchTags)
	{

		$results = array_merge(
			$this->getRelationsByTags($searchTags),
			$this->getWaysByTags($searchTags),
			$this->getNodesByTags($searchTags)
		);
		return $results;
	}

	/**
	 * Returns all loaded nodes which are matching tags attributes
	 *
	 * @param array $tags is a array of Key=>Value.
	 * @return Node[]
	 */
	public function &getRelationsByTags(array $searchTags)
	{

		$results = array();
		foreach ($this->_relations as $obj) {
			if ($obj->hasTags($searchTags))
				$results[] = $obj;
		}
		return $results;
	}

	/**
	 * Returns all loaded nodes which are matching tags attributes
	 *
	 * @param array $tags is a array of Key=>Value.
	 * @return Node[]
	 */
	public function &getWaysByTags(array $searchTags)
	{

		$results = array();
		foreach ($this->_ways as $obj) {
			if ($obj->hasTags($searchTags))
				$results[] = $obj;
		}
		return $results;
	}

	/**
	 * Returns all loaded nodes which are matching tags attributes
	 *
	 * @param array $tags is a array of Key=>Value.
	 * @return Node[]
	 */
	public function &getNodesByTags(array $searchTags)
	{

		$results = array();
		foreach ($this->_nodes as $obj) {
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
	public function removeObject($type, $id)
	{

		switch ($type) {
			case OSM_Object::OBJTYPE_RELATION:
				if (array_key_exists($id, $this->_relations))
					unset($this->_relations[$id]);
				break;

			case OSM_Object::OBJTYPE_WAY:
				if (array_key_exists($id, $this->_ways))
					unset($this->_ways[$id]);
				break;

			case OSM_Object::OBJTYPE_NODE:
				if (array_key_exists($id, $this->_nodes))
					unset($this->_nodes[$id]);
				break;

			default:
				throw new OSM_Exception('Unknow object type "' . $type . '"');
				break;
		}
	}

	public function removeAllObjects()
	{
		$this->_relations = $this->_ways = $this->_nodes = [];
	}

	/**
	 * Reload a given OSM Object into the objects collection.
	 *
	 * It remove the object before.
	 *
	 * @param string $type
	 * @param int $id
	 * @param bool $full
	 * @return Object the reverted object
	 */
	public function reloadObject($type, $id, $full = false)
	{

		$this->removeObject($type, $id);
		return $this->getObject($type, $id, $full);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $qlQuery
	 * @return void
	 */
	public function queryOApiQL($qlQuery)
	{
		$url = $this->_options['oapi_url'];
		$method = 'POST';

		$this->getLogger()->debug('{_m} url:{_u} query:{_q}', ['_m' => $method, '_u' => $url, '_q' => $qlQuery]);

		$postdata = http_build_query(array('data' => $qlQuery));

		$opts = [
			'http' =>
			[
				'ignore_errors' => true,
				'method' => $method,
				'user_agent' => $this->_getUserAgent(),
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			]
		];
		$context = stream_context_create($opts);

		$this->_stats['requestCount']++;

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('out', $url, $method), $qlQuery);
		}

		$cacheFile = $this->_getCacheFilename($url, $method, $postdata);
		$result = null;

		if ($this->_options['cacheFolder'] != null && (!$this->cacheDisabled)) {
			if (file_exists($cacheFile)) {
				$this->getLogger()->notice('Read from cache {method} {http_method} {url}', ['method' => __METHOD__, 'http_method' => $method, 'url' => $url]);
				$result = @file_get_contents($cacheFile);
			}
		}

		if (! $result) {
			$this->getLogger()->notice('{method} {http_method} {url}', ['method' => __METHOD__, 'http_method' => $method, 'url' => $url]);
			$this->getLogger()->debug('{_m} opts:{opts}', ['opts' => $opts, '_m' => __METHOD__]);
			$result = @file_get_contents($url, false, $context);
			if ($this->_options['cacheFolder'] != null && (!$this->cacheDisabled)) {
				file_put_contents($cacheFile, $result);
			}
		}

		if ($result === false) {
			$e = error_get_last();
			if (isset($http_response_header)) {
				throw new HttpException('message: ' . $e['message'] . ', http_response_header: ' . print_r($http_response_header, true));
			} else {
				throw new HttpException($e['message']);
			}
		}

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('in', $url, $method), $result);
		}

		$this->_stats['loadedBytes'] += strlen($result);

		$this->createObjectsfromXml($result);
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
	public function queryOApiXml($xmlQuery, $withMeta = true)
	{
		if ($withMeta) {
			$this->_oapiAddMetadata($xmlQuery);
		}
		$this->getLogger()->debug('{_m} url:{url} query:{query}', ['_m' => __METHOD__, 'query' => $xmlQuery]);

		$url = $this->_options['oapi_url'];
		$method = 'POST';
		$postdata = http_build_query(array('data' => $xmlQuery));

		$opts = [
			'http' =>
			[
				'method' => $method,
				'user_agent' => $this->_getUserAgent(),
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			]
		];
		$context = stream_context_create($opts);

		$this->getLogger()->notice('{method} {http_method} {url}', ['method' => __METHOD__, 'http_method' => $method, 'url' => $url]);
		$this->getLogger()->debug('{_m} opts:{opts}', ['opts' => $opts, '_m' => __METHOD__]);

		$this->_stats['requestCount']++;

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('out', $url, $method), $xmlQuery);
		}

		$result = file_get_contents($url, false, $context);
		if ($result === false) {
			$e = error_get_last();
			if (isset($http_response_header)) {
				throw new HttpException($http_response_header);
			} else {
				throw new HttpException($e['message']);
			}
		}

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('in', $url, $method), $result);
		}

		$this->_stats['loadedBytes'] += strlen($result);

		$this->createObjectsfromXml($result);
	}

	/**
	 *
	 * @param string $xmlQuery
	 */
	protected function _oapiAddMetadata(&$xmlQuery)
	{
		$this->getLogger()->debug('{_m}', ['_m' => __METHOD__]);

		$x = new \SimpleXMLElement($xmlQuery);
		$xPrints = $x->xpath('//print');
		foreach ($xPrints as $xPrint) {
			if ($xPrint['mode'] == null) {
				$xPrint->addAttribute('mode', 'meta');
			}
		}
		$xmlQuery = $x->asXml();
	}

	/**
	 * Querying a XAPI instance.
	 * For each matching relation|way the way|nodes referenced by that relation|way are also returned.
	 *
	 * - API documentation: https://wiki.openstreetmap.org/wiki/Xapi
	 * - Some xapi implementations differ from the specification.
	 * - Add "[@meta]" to query if you want metadata (version, timestamp, changeset, user).
	 *
	 * @param string $query
	 * @return void
	 */
	public function queryXApi($query)
	{
		$this->_logger->debug(__METHOD__ . ' Query:{query}', ['query' => $query]);

		$url = $this->_options['xapi_url'];
		$method = 'GET';

		$opts = array(
			'http' =>
			array(
				'method' => $method,
				'user_agent' => $this->_getUserAgent(),
			)
		);
		$context = stream_context_create($opts);

		$this->getLogger()->notice('{_m} {http_method} {url}', ['_m' => __METHOD__, 'http_method' => $method, 'url' => $url]);

		$result = file_get_contents($url . '?' . urlencode($query), false, $context);

		$this->_stats['requestCount']++;

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('out', $url, $method), $query);
		}

		if ($result === false) {
			$e = error_get_last();
			if (isset($http_response_header)) {
				throw new HttpException($http_response_header);
			} else {
				throw new HttpException($e['message']);
			}
		}

		if ($this->_options['outputFolder'] != null) {
			file_put_contents($this->_getOutputFilename('in', $url, $method), $result);
		}

		$this->_stats['loadedBytes'] += strlen($result);

		$this->createObjectsfromXml($result);
	}

	/**
	 * Create and add a new node to the objects collection.
	 *
	 * @param type $lat
	 * @param type $lon
	 * @param array $tags
	 * @return Node
	 */
	public function addNewNode($lat = 0, $lon = 0, array $tags = null)
	{

		$node = new Node($this->_newIdCounter--, $lat, $lon, $tags);
		$this->_nodes[$node->getId()] = $node;
		return $node;
	}

	/**
	 * Create and add a new way to the objects collection.
	 *
	 * @param array $nodes
	 * @param array $tags
	 * @return Way
	 */
	public function addNewWay(array $nodes = null, array $tags = null)
	{

		$way = new Way($this->_newIdCounter--);
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
	 * @return Relation
	 */
	public function addNewRelation(array $members = null, array $tags = null)
	{

		$relation = new Relation($this->_newIdCounter--);
		if (is_array($members))
			$relation->addMembers($members);
		if (is_array($tags))
			$relation->addTags($tags);
		return $relation;
	}

	/**
	 *
	 * @return OSM_Object[]
	 */
	public function &getDirtyObjects()
	{

		$dirtyObjects = array();

		// union of objects
		$objects = $this->_relations + $this->_ways + $this->_nodes;

		foreach ($objects as $obj) {
			$this->getLogger()->debug('{_m} Object {class}/{id} is dirty:{dirty}', ['_m' => __METHOD__, 'class' => get_class($obj), 'id' => $obj->getId(), 'dirty' => $obj->isDirty()]);
			if ($obj->isDirty()) {
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
	public function getXmlDocument($onlyDirtyObjects = false)
	{
		$xml = '<osm version="0.6" upload="true" generator="' . $this->_getUserAgent() . '">' . "\n";
		// union of objects
		$objects = $this->_relations + $this->_ways + $this->_nodes;
		foreach ($objects as $obj) {
			if ($onlyDirtyObjects) {
				if ($obj->isDirty()) {
					$xml .= $obj->asXmlStr() . "\n";
				}
			} else {
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
	 * @return ChangeSet if has saved something, null if nothing to save.
	 * @throws OSM_Exception if not authenticated
	 */
	public function saveChanges($comment)
	{
		$this->getLogger()->notice('{_m} comment:"{comment}"', ['_m' => __METHOD__, 'comment' => $comment]);

		if (!$this->isAuthenticated())
			throw new OSM_Exception('Must be authenticated');

		if ($this->isSimulation()) {
			$this->getLogger()->notice(__METHOD__ . ' Simulation Mode, not saving'
				. ($this->_options['outputFolder'] != null
					? ' but look inside folder ' . $this->_options['outputFolder']
					: ''));
		}

		$dirtyObjects = $this->getDirtyObjects();
		$dirtyObjectsCount = count($dirtyObjects);

		if ($dirtyObjectsCount == 0) {
			$this->getLogger()->notice(__METHOD__ . ' No dirty object, skip save');
			return null;
		}
		$this->getLogger()->notice(__METHOD__ . ' Has ' . $dirtyObjectsCount . ' dirty objects');

		$changeSet = $this->_createChangeSet($comment);

		$changeSetId = $changeSet->getId();

		foreach ($dirtyObjects as $obj) {
			//OSM_ZLog::debug(__METHOD__,print_r($obj,true));

			if ($obj->isDeleted()) {
				$changeSet->deleteObject($obj);
			} else {
				$changeSet->addObject($obj);
			}
		}

		$this->_uploadChangeSet($changeSet);

		$this->_closeChangeSet($changeSet);

		// issue #17 ??
		$this->_clearObjects();

		return $changeSet;
	}

	protected function _clearObjects()
	{
		$this->_relations = $this->_ways = $this->_nodes = [];
	}

	/**
	 *
	 * @param string $comment
	 * @return ChangeSet
	 */
	protected function _createChangeSet($comment)
	{
		$relativeUrl = '/changeset/create';

		if ($this->isSimulation()) {
			$this->getLogger()->info(__METHOD__ . ' Simulation Mode, set changeset id to 999');
			$result = 999;
		} else {
			$result = $this->_httpApi($relativeUrl, ChangeSet::getCreateXmlStr($comment, $this->_getUserAgent()), 'PUT');
		}

		$this->getLogger()->debug('{_m} result:{result}', ['_m' => __METHOD__, 'result' => $result]);

		$changeSet = new ChangeSet($result);
		return $changeSet;
	}

	protected function _closeChangeSet($changeSet)
	{

		$relativeUrl = '/changeset/' . $changeSet->getId() . '/close';

		if ($this->isSimulation()) {
		} else {
			$result = $this->_httpApi($relativeUrl, null, 'PUT');
		}
	}

	protected function _uploadChangeSet(ChangeSet $changeSet)
	{
		$this->getLogger()->notice('{_m} uploading changeset id:"{id}"', ['_m' => __METHOD__, 'id' => $changeSet->getId()]);

		$relativeUrl = '/changeset/' . $changeSet->getId() . '/upload';

		$xmlStr = $changeSet->getUploadXmlStr($this->_getUserAgent());

		if ($this->_options['outputFolder']) {
			file_put_contents($this->_getOutputFilename('out', '_uploadChangeSet', 'POST'), $xmlStr);
		}

		if ($this->isSimulation()) {
			$result = 'Simulation, no call to Api';
		} else {
			$result = $this->_httpApi($relativeUrl, $xmlStr, 'POST');
		}

		$this->getLogger()->debug(__METHOD__ . ' result:{result}', ['result' => $result]);
	}

	/**
	 * Tell if the Node is inside the polygon defined by the Relation.
	 * Note: Does not works with multipolygon (outer/inner/...)
	 *
	 * @param Node $node2test
	 * @param Relation $relation
	 * @return bool
	 */
	public function isNodeInsideRelationPolygon(Node $node2test, Relation $relation)
	{

		$poly1 = $this->getPolygon($relation);

		$poly2 = new Polygon();
		$poly2->addv($node2test->getLat(), $node2test->getLon());

		return $poly1->isPolyInside($poly2);
	}

	/**
	 *
	 * @param Relation $relation
	 * @return \OSM\Tools\Polygon
	 */
	public function getPolygon(Relation $relation)
	{

		require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'OSM' . DIRECTORY_SEPARATOR . 'Tools' . DIRECTORY_SEPARATOR . 'Polygon.php';
		$poly = new Polygon();

		$ways = $this->getRelationWaysOrdered($relation);
		$waysCount = count($ways);


		$lastviewNodeId = 0;
		if ($ways[0]->getFirstNodeRef() == $ways[$waysCount - 1]->getFirstNodeRef() || $ways[0]->getFirstNodeRef() == $ways[$waysCount - 1]->getLastNodeRef()) {
			$lastviewNodeId = $ways[0]->getFirstNodeRef();
		} else {
			$lastviewNodeId = $ways[0]->getLastNodeRef();
		}

		for ($wi = 0; $wi < $waysCount; $wi++) {
			$way = $ways[$wi];
			$nodeRefs = $way->getNodesRefs();
			if ($lastviewNodeId == $way->getFirstNodeRef()) {
				// à l'endroit
				//echo 'draw '.$way->getId() .' -> '."\n";
				for ($i = 0; $i < count($nodeRefs); $i++) {
					$nodeRef = $nodeRefs[$i];
					$node = $this->_nodes[$nodeRef];
					$poly->addv($node->getLat(), $node->getLon());
				}
			} else {
				// à l'envers
				//echo 'draw '.$way->getId() .' <- '."\n";
				for ($i = count($nodeRefs) - 1; $i >= 0; $i--) {
					$nodeRef = $nodeRefs[$i];
					$node = $this->_nodes[$nodeRef];
					$poly->addv($node->getLat(), $node->getLon());
				}
			}

			$toto = ($wi + 1) % $waysCount;
			if ($nodeRef == $ways[$toto]->getLastNodeRef()) {
				$lastviewNodeId = $ways[$toto]->getLastNodeRef();
			} else {
				$lastviewNodeId = $ways[$toto]->getFirstNodeRef();
			}
		}
		return $poly;
	}

	/**
	 * Return relation's way ordered by nodes position, like we can draw a well formed polygon.
	 *
	 * @param Relation $relation
	 * @return Way[]
	 */
	public function getRelationWaysOrdered(Relation $relation)
	{

		$membersWays = $relation->findMembersByType(OSM_Object::OBJTYPE_WAY);

		$w1 = $membersWays[0];
		if (!array_key_exists($w1->getRef(), $this->_ways)) {
			throw new OSM_Exception('Way not loaded, you must load the full relation.');
		}

		$waysOrderedIds = array();
		$waysOrdered = array();

		$ww1 = $this->_ways[$w1->getRef()];
		$waysOrderedIds[$ww1->getId()] = $ww1;
		$waysOrdered[] = $ww1;
		for ($i = 0; $i < count($membersWays); $i++) {
			if (!array_key_exists($w1->getRef(), $this->_ways)) {
				throw new OSM_Exception('Way not loaded, you must load the full relation.');
			}
			$ww1 = $this->_ways[$w1->getRef()];
			for ($j = 0; $j < count($membersWays); $j++) {
				$w2 = $membersWays[$j];
				if ($w1->getRef() == $w2->getRef())
					continue;
				if (array_key_exists($w2->getRef(), $waysOrderedIds)) {
					continue;
				}

				if (!array_key_exists($w2->getRef(), $this->_ways)) {
					throw new OSM_Exception('Way not loaded, you must load the full relation.');
				}
				$ww2 = $this->_ways[$w2->getRef()];

				$nId1F = $ww1->getFirstNodeRef();
				$nId1L = $ww1->getLastNodeRef();
				$nId2F = $ww2->getFirstNodeRef();
				$nId2L = $ww2->getLastNodeRef();

				if ($nId1F == $nId2F || $nId1F == $nId2L || $nId1L == $nId2F || $nId1L == $nId2L) {
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
	public function &getWayNodesCoordinates(Way $way)
	{
		$coords = array();
		$nodesRef = $way->getNodesRefs();
		$n = count($nodesRef);
		for ($i = 0; $i < $n; $i++) {
			$node = $this->getNode($nodesRef[$i]);
			$coords[] = array($node->getLon(), $node->getLat());
		}
		return $coords;
	}

	public function getStats()
	{
		// Refresh objects count
		$this->_stats['Objects'] = count($this->getObjects());
		return $this->_stats;
	}

	public function getStatsRequestCount()
	{
		return $this->_stats['requestCount'];
	}

	public function getStatsLoadedBytes()
	{
		return $this->_stats['loadedBytes'];
	}

	/**
	 * Return a string like "MyApp / Yapafo 0.1", based on the "appName" options and the library constants.
	 * This appears as the editor's name in the changeset properties (key "crated_by")
	 * @return string user agent string
	 */
	protected function _getUserAgent()
	{
		$userAgent = "";
		if ($this->_options['appName'] != "") {
			$userAgent .= $this->_options['appName'] . ' / ';
		}
		$userAgent .= self::USER_AGENT . ' v' . self::VERSION;
		return $userAgent;
	}

	protected function _getOutputFilename($inOrOut, $relativeUrl, $method)
	{
		/**
		 * Used to construct the filename.
		 * @var int
		 */
		static $outputWriteCount = 0;

		// file_put_contents($this->_getOutputFilename('in', $relativeUrl, $method, $data))

		return $this->_options['outputFolder'] .
			DIRECTORY_SEPARATOR . __CLASS__
			. '_' . sprintf('%04d', ++$outputWriteCount) . '-' . time()
			. '_' . $inOrOut . '-' . $method . '-' . urlencode($relativeUrl) . '.txt';
	}

	protected function _getCacheFilename($url, $method, $data)
	{
		$dataId = sha1(serialize($data));
		return $this->_options['cacheFolder'] .
			DIRECTORY_SEPARATOR . __CLASS__
			. '_' . 'cache' . '-' . $method . '-' . md5($url) . '-' . $dataId . '.txt';
	}

	/**
	 * Implements GET /api/0.6/permissions.
	 * When NO or INVALID Access Token, no error but a empty permissions set.
	 *
	 * https://github.com/openstreetmap/openstreetmap-website/pull/45
	 *
	 * @param bool $force Default is false: reuse previous results without asking to server.
	 */
	public function getAuthPermissions($force = false)
	{
		/**
		 * @var array
		 */
		static $cachedPermissions;

		//$this->getLogger()->debug('{_m} force:{force} perms:{perms}', ['_m'=>__METHOD__, 'force'=>$force, 'perms'=>$cachedPermissions]);

		if (!$this->isAuthenticated())
			throw new OSM_Exception('Must be authenticated');

		if ((!$force) && ($cachedPermissions != null)) {
			return $cachedPermissions;
		}

		$result = $this->_httpApi('/permissions');

		/*
		$this->getLogger()->debug(__METHOD__.' result:{result}', ['result'=>$result]);
		<osm version="0.6">
		  <permissions>
			<permission name="allow_read_prefs"/>
			<permission name="allow_write_prefs"/>
			<permission name="allow_write_diary"/>
			<permission name="allow_write_api"/>
			<permission name="allow_read_gpx"/>
			<permission name="allow_write_gpx"/>
			<permission name="allow_write_notes"/>
		  </permissions>
		</osm>
		When Token is INVALID, no error but a empty permissions set
		<osm version="0.6">
			<permissions>
			</permissions>
		</osm>
		 */

		$x = new \SimpleXMLElement($result);
		$perms = $x->xpath('/osm/permissions/permission');

		$cachedPermissions = [];
		foreach ($perms as $perm) {
			$cachedPermissions[] = (string) $perm['name'];
		}

		return $cachedPermissions;
	}

	/**
	 * @return bool allow_read_prefs
	 */
	public function isAllowedTo($perms, $force = false)
	{
		return in_array($perms, $this->getAuthPermissions($force));
	}

	/**
	 * HTTP/1.1 401 Unauthorized is not logged in.
	 *
	 * @return UserDetails
	 * @throws OSM_Exception if not authenticated
	 */
	public function getUserDetails()
	{

		if (!$this->isAuthenticated())
			throw new OSM_Exception('Must be authenticated');

		$result = $this->_httpApi('/user/details');

		$this->getLogger()->debug(__METHOD__ . ' result:{result}', ['result' => $result]);

		return UserDetails::createFromXmlString($result);
	}

	/**
	 * Retreive all user preferences from openstreetmap.org website.
	 *
	 * @return array
	 */
	public function getUserPreferences()
	{

		if (!$this->isAuthenticated())
			throw new OSM_Exception('Must be authenticated');

		$result = $this->_httpApi('/user/preferences');

		$this->getLogger()->debug(__METHOD__ . ' result:{result}', ['result' => $result]);

		$prefs = array();

		$x = new \SimpleXMLElement($result);
		foreach ($x->preferences->children() as $p) {
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
	public function setUserPreference($key, $value)
	{

		if (!$this->isAuthenticated())
			throw new OSM_Exception('Must be authenticated');

		$result = $this->_httpApi(
			'/user/preferences/' . rawurlencode(utf8_encode($key)),
			rawurlencode(utf8_encode($value)),
			'PUT'
		);

		$this->getLogger()->debug(__METHOD__ . ' result:{result}', ['result' => $result]);
	}
}
