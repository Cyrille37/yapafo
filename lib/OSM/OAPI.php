<?php

/**
 * OSM/OAPI.class.php
 */
require_once ( __DIR__ . '/OAPIResponse.php');

/**
 * Description of OSM_OAPI
 *
 * Overpass API/Language Guide :
 * http://wiki.openstreetmap.org/wiki/Overpass_API
 * http://wiki.openstreetmap.org/wiki/Overpass_API/Language_Guide
 * 
 * @author cyrille
 */
class OSM_OAPI {
	const VERSION = '0.1';
	const USER_AGENT = 'OSM_OAPI-Php http://www.openstreetmap.org/user/Cyrille37';

	/**
	 * Query form: http://api.openstreetmap.fr/query_form.html
	 */
	const OAPI_URL_FR = 'http://api.openstreetmap.fr/oapi/interpreter';
	const OAPI_URL_RU = 'http://overpass.osm.rambler.ru/';
	const OAPI_URL_LETUFFE = 'http://overpassapi.letuffe.org/api/interpreter';
	const OAPI_URL_DE = 'http://www.overpass-api.de/api/interpreter';

	protected $_options = array(
		'url' => self::OAPI_URL_FR,
		'debug' => false,
		'cache' => null
	);

	/**
	 * @var array
	 */
	protected $_stats = array(
		'requestCount' => 0,
		'loadedBytes' => 0,
		'cacheHits' => 0
	);

	public function __construct($options = array()) {

		if (!empty($options['cache']))
		{
			if (!method_exists($options['cache'], 'load'))
				throw new Exception('Cache engine is not compatible, miss method load()');
			if (!method_exists($options['cache'], 'save'))
				throw new Exception('Cache engine is not compatible, miss method save()');
		}

		// Check that all options exist then override defaults
		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, $this->_options))
				throw new OSM_Exception('Unknow Api option "' . $k . '"');
			$this->_options[$k] = $v;
		}
	}

	public function getUrl() {
		return $this->_options['url'];
	}

	public function getStatsRequestCount() {
		return $this->_stats['requestCount'];
	}

	public function getStatsLoadedBytes() {
		return $this->_stats['loadedBytes'];
	}

	public function getStatsCacheHits() {
		return $this->_stats['cacheHits'];
	}

	/**
	 *
	 * @param string $xmlQuery
	 * @param bool $forceNoCache
	 * @return OSM_OAPIResponse 
	 */
	public function request($xmlQuery, $forceNoCache=false) {

		$this->_dbg(__METHOD__, $xmlQuery);

		$this->_stats['requestCount']++;

		if (!$forceNoCache)
		{
			$result = $this->_cacheLoad(md5($xmlQuery));
			if ($result != null)
				return $result;
		}

		$postdata = http_build_query(array('data' => $xmlQuery));
		$opts = array('http' =>
			array(
				'method' => 'POST',
				'user_agent' => OSM_OAPI::USER_AGENT . ' ' . OSM_OAPI::VERSION,
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			)
		);
		$context = stream_context_create($opts);

		$result = file_get_contents($this->_options['url'], false, $context);

		$this->_stats['loadedBytes'] += strlen($result);

		$this->_cacheSave(md5($xmlQuery), $result);

		$response = new OSM_OAPIResponse($result);
		return $response;
	}

	protected function _cacheLoad($key) {

		if (!isset($this->_options['cache']))
		{
			$this->_dbg(__METHOD__, 'no cache engine set');
			return null;
		}
		$res = $this->_options['cache']->load($key);

		if ($res != null)
			$this->_stats['cacheHits']++;

		if ($this->_options['debug'])
		{
			if ($res == null)
				$this->_dbg(__METHOD__, 'not found');
			else
				$this->_dbg(__METHOD__, 'got it');
		}

		return $res;
	}

	protected function _cacheSave($key, $value) {

		if (!isset($this->_options['cache']))
		{
			$this->_dbg(__METHOD__, 'no cache engine set');
			return null;
		}
		$this->_dbg(__METHOD__, 'save');
		$this->_options['cache']->save($value, $key);
	}

	protected function _dbg($who, $str='') {
		if ($this->_options['debug'])
		{
			echo '[dbg][' . $who . '] ' . $str . "\n";
		}
	}

}
