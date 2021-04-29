<?php
namespace Cyrille37\OSM\Yapafo ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Tools\Logger;
use Psr\Log\LogLevel;
use Cyrille37\OSM\Yapafo\OApiResponse ;

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

	const VERSION = '2.0';
	const USER_AGENT = 'https://github.com/Cyrille37/yapafo';

	/**
	 * Query form: http://api.openstreetmap.fr/query_form.html
	 */
	const OAPI_URL_FR = 'https://api.openstreetmap.fr/oapi/interpreter';
	const OAPI_URL_RU = 'https://overpass.osm.rambler.ru/';
	const OAPI_URL_LETUFFE = 'https://overpassapi.letuffe.org/api/interpreter';
	const OAPI_URL_DE = 'https://www.overpass-api.de/api/interpreter';

	protected $_options = array(
		'url' => self::OAPI_URL_DE,
		'log' => [
			'logger' => null ,
			'level' => LogLevel::DEBUG
		],
	);

	/**
	 * @var array
	 */
	protected $_stats = array(
		'requestCount' => 0,
		'loadedBytes' => 0
	);

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger ;

	public function __construct( $options = [] )
	{
		// Check that all options exist then override defaults
		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, $this->_options))
				throw new OSM_Exception('Unknow Api option "' . $k . '"');
			$this->_options[$k] = $v;
		}

		if( isset($this->_options['log']['logger']) )
		{
			$this->_logger = $this->_options['log']['logger'];
		}
		else
		{
			$this->_logger = Logger::getInstance( $this->_options['log']['level'] );
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
	 * @return OApiResponse 
	 */
	public function request($xmlQuery)
	{
		$this->getLogger(__METHOD__.' query:{query}', ['query'=>$xmlQuery]);

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

		$response = new OApiResponse($result);
		return $response;
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	public function getLogger()
	{
		return $this->_logger ;
	}

}
