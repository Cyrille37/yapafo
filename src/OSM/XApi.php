<?php
namespace Cyrille37\OSM\Yapafo ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Tools\Logger;
use Psr\Log\LogLevel;

/**
 * Documentation:
 * - https://wiki.openstreetmap.org/wiki/Xapi
 */
class OSM_XApi
{
	const VERSION = '2.0';
	const USER_AGENT = 'https://github.com/Cyrille37/yapafo';

	/**
	 * http://www.overpass-api.de/api/xapi
	 * http://api.openstreetmap.fr/xapi
	 * deprecated: http://overpassapi.letuffe.org/api/xapi
	 */
	const XAPI_URL_DE = 'http://www.overpass-api.de/api/xapi';
	const XAPI_URL_FR = 'http://api.openstreetmap.fr/xapi';
	const XAPI_URL_LETTUFE = 'http://overpassapi.letuffe.org/api/xapi';

	protected $_options = array(
		'xapi_url' => self::XAPI_URL_DE,
		'cache'=>null,
		'log' => [
			'logger' => null ,
			'level' => LogLevel::DEBUG
		],
	);

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger ;

	public function __construct( $options = array() )
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

		if (!empty($options['cache']))
		{
			if (!method_exists($options['cache'], 'load'))
				throw new OSM_Exception('Cache engine is not compatible, miss method load()');
			if (!method_exists($options['cache'], 'save'))
				throw new OSM_Exception('Cache engine is not compatible, miss method save()');
		}

	}

	public function request( $query )
	{
		$this->_logger->debug( __METHOD__.' Query:{query}', ['query'=>$query]);

		$opts = array('http' =>
			array(
				'method' => 'GET',
				'user_agent' => OSM_XAPI::USER_AGENT . ' ' . OSM_XAPI::VERSION
			)
		);
		$context = stream_context_create($opts);

		$result = file_get_contents($this->_options['xapi_url'] . '?' . urlencode($query), false, $context);

		return $result;
	}

}
