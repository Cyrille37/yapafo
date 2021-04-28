<?php

/**
 * OSM/OApi.class.php
 */
require_once ( __DIR__ . '/OApiResponse.php');

/**
 * Description of OSM_OApi
 *
 * Overpass API/Language Guide :
 * http://wiki.openstreetmap.org/wiki/Overpass_API
 * http://wiki.openstreetmap.org/wiki/Overpass_API/Language_Guide
 * 
 * @author cyrille
 */
class OSM_OApi {

	const VERSION = '0.2';
	const USER_AGENT = 'Yapafo OSM_OApi http://yapafo.net';

	/**
	 * Query form: http://api.openstreetmap.fr/query_form.html
	 */
	const OAPI_URL_FR = 'http://api.openstreetmap.fr/oapi/interpreter';
	const OAPI_URL_RU = 'http://overpass.osm.rambler.ru/';
	const OAPI_URL_LETUFFE = 'http://overpassapi.letuffe.org/api/interpreter';
	const OAPI_URL_DE = 'http://www.overpass-api.de/api/interpreter';

	protected $_options = array(
		'url' => self::OAPI_URL_FR,
		'debug' => false
	);

	/**
	 * @var array
	 */
	protected $_stats = array(
		'requestCount' => 0,
		'loadedBytes' => 0
	);

	public function __construct($options = array()) {

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

	/**
	 *
	 * @param string $xmlQuery
	 * @param bool $forceNoCache
	 * @return OSM_OApiResponse 
	 */
	public function request($xmlQuery) {

		$this->_dbg(__METHOD__, $xmlQuery);

		$this->_stats['requestCount']++;

		$postdata = http_build_query(array('data' => $xmlQuery));
		$opts = array('http' =>
			array(
				'method' => 'POST',
				'user_agent' => OSM_OApi::USER_AGENT . ' v' . OSM_OApi::VERSION,
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			)
		);
		$context = stream_context_create($opts);

		$result = file_get_contents($this->_options['url'], false, $context);

		$this->_stats['loadedBytes'] += strlen($result);

		$response = new OSM_OApiResponse($result);
		return $response;
	}

	protected function _dbg($who, $str='') {
		if ($this->_options['debug'])
		{
			if (PHP_SAPI === 'cli')
			{
				echo('[dbg][' . $who . '] ' . $str . "\n" );
			}
			else
			{
				error_log('[dbg][' . $who . '] ' . $str . "\n" );				
			}
		}
	}

}
