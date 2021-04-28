<?php
/**
 * OSM/XAPI.class.php
 */

/**
 * Description of OSM_XAPI
 *
 * @author cyrille
 */
class OSM_XAPI {

	const VERSION = '0.1';
	const USER_AGENT = 'OSM_XAPI-Php http://www.openstreetmap.org/user/Cyrille37';

	/**
	 * http://www.overpass-api.de/api/xapi
	 * http://api.openstreetmap.fr/xapi
	 * deprecated: http://overpassapi.letuffe.org/api/xapi
	 */
	const XAPI_URL_DE = 'http://www.overpass-api.de/api/xapi';
	const XAPI_URL_FR = 'http://api.openstreetmap.fr/xapi';
	const XAPI_URL_LETTUFE = 'http://overpassapi.letuffe.org/api/xapi';
	const XAPI_REQ_NODE = 'node';
	const XAPI_REQ_WAY = 'way';
	const XAPI_REQ_RELATION = 'relation';
	const XAPI_REQ_ANY = '*';

	protected $_options = array(
		'xapi_url' => self::XAPI_URL_FR,
		'debug'=>false,
		'cache'=>null
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

	public function request($query, $req_type = OSM_XAPI::XAPI_REQ_ANY) {

		$this->_dbg(__METHOD__, $query);

		$result = $this->_cacheLoad(md5($req_type . $query));
		if ($result != null)
			return $result;

		$opts = array('http' =>
			array(
				'method' => 'GET',
				'user_agent' => OSM_XAPI::USER_AGENT . ' ' . OSM_XAPI::VERSION
			)
		);
		$context = stream_context_create($opts);

		$result = file_get_contents($this->_options['xapi_url'] . '?' . $req_type . urlencode($query), false, $context);

		$this->_cacheSave(md5($req_type . $query), $result);
		return $result;
	}

	protected function _cacheLoad($key) {

		if (!isset($this->_options['cache']))
		{
			$this->_dbg(__METHOD__, 'no cache engine set');
			return null;
		}
		$res = $this->_options['cache']->load($key);
		if (self::$DEBUG)
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
