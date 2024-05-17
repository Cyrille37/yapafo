<?php
namespace Cyrille37\OSM\Yapafo\Auth ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Exceptions\HttpException ;

/**
 * Class OSM_OAuth implement OAuth 1 (Open Authorization)
 *
 * http://wiki.openstreetmap.org/wiki/OAuth
 * http://oauth.net/documentation/
 * https://oauth.net/core/1.0a/
 * http://tools.ietf.org/html/rfc5849
 */
class OAuth1 implements IAuthProvider {

	const BASE_URL_PROD = 'https://www.openstreetmap.org';
	const BASE_URL_DEV = 'https://master.apis.dev.openstreetmap.org';

	const PROTOCOL_VERSION = '1.0';
	const SIGNATURE_METHOD = 'HMAC-SHA1';
	const USER_AGENT = 'Yapafo OSM_OAuth https://github.com/Cyrille37/yapafo' ;

	protected $_options = array(
		'base_url' => self::BASE_URL_DEV,
		'requestTokenUrl' => '/oauth/request_token',
		'accessTokenUrl' => '/oauth/access_token',
		'authorizeUrl' => '/oauth/authorize',
		'callback_url' => null ,
	);
	protected $_consKey;
	protected $_consSec;
	protected $_requestToken;
	protected $_requestTokenSecret;
	protected $_accessToken;
	protected $_accessTokenSecret;
	/**
	 * Required if a custom callback_url is specified.
	 * @see requestAccessToken()
	 * @see _prepareParameters()
	 * @var string
	 */
	protected $_requestOAuthVerifier ;
	protected $_timestamp;

	public function __construct($consumerKey, $consumerSecret, $options = array()) {

		/*
		if (empty($consumerKey))
			throw new OSM_Exception('Credential "consumerKey" must be set');
		if (empty($consumerSecret))
			throw new OSM_Exception('Credential "consumerSecret" must be set');
		*/
		// Check that all options exist then override defaults
		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, $this->_options))
				throw new OSM_Exception('Unknow ' . __CLASS__ . ' option "' . $k . '"');
			$this->_options[$k] = $v;
		}

		$this->_consKey = $consumerKey;
		$this->_consSec = $consumerSecret;
	}

	public function getOptions()
	{
		return $this->_options ;
	}

	public function setAccessToken($token, $tokenSecret) {

		$this->_accessToken = $token;
		$this->_accessTokenSecret = $tokenSecret;
	}

	/**
	 * Return access Token and it's secret.
	 * @return array array('token' => string, 'tokenSecret' => string) 
	 */
	public function getAccessToken() {

		return array(
			'token' => $this->_accessToken,
			'tokenSecret' => $this->_accessTokenSecret
		);
	}

	public function deleteAccessAuthorization()
	{
		$this->_accessToken = null ;
		$this->_accessTokenSecret = null ;
		$this->_requestToken = null ;
		$this->_requestTokenSecret = null ;
	}

	/**
	 * @return boolean Has got an access token which permit to act as a user.
	 */
	public function hasAccessToken() {

		if (!empty($this->_accessToken) && !empty($this->_accessTokenSecret))
			return true;
		return false;
	}
	
	public function setRequestToken($token, $tokenSecret) {

		$this->_requestToken = $token;
		$this->_requestTokenSecret = $tokenSecret;
	}

	/**
	 * Return request Token and it's secret.
	 * @return array array('token' => string, 'tokenSecret' => string) 
	 */
	public function getRequestToken()
	{
		return array(
			'token' => $this->_requestToken,
			'tokenSecret' => $this->_requestTokenSecret
		);		
	}
	
	public function requestAuthorizationUrl() {

		$result = $this->_http($this->_options['base_url'].$this->_options['requestTokenUrl']);

		$tokenParts = null ;
		parse_str($result, $tokenParts);
		//echo 'requestAuthorizationUrl: '.print_r( $tokenParts ,true)."\n";

		$this->_requestToken = $tokenParts['oauth_token'];
		$this->_requestTokenSecret = $tokenParts['oauth_token_secret'];

		return array(
			'url' => $this->_options['base_url'].$this->_options['authorizeUrl'] . '?oauth_token=' . $this->_requestToken,
			'token' => $this->_requestToken,
			'tokenSecret' => $this->_requestTokenSecret
		);
	}

	/**
	 *
	 * @param string $oauth_verifier Only used if a callback url is specified.
	 * @return array Contains 'token' and 'tokenSecret'.
	 */
	public function requestAccessToken( $oauth_verifier=null )
	{
		// Required if a custom callback url is specified
		$this->_requestOAuthVerifier = $oauth_verifier ;

		$result = $this->_http($this->_options['base_url'].$this->_options['accessTokenUrl']);

		// FIXME: Not so nice, but easiest ;-)
		// this should be an parameter not a property, but it's easiest like that ...
		// AND is it required to clean this value ? Is this value associated with authorization or access ?
		// Please, make add a test !
		$this->_requestOAuthVerifier = null;

		$tokenParts = null ;
		parse_str($result, $tokenParts);
		//echo 'requestAccessToken: '.print_r( $tokenParts ,true)."\n";

		$this->_accessToken = $tokenParts['oauth_token'];
		$this->_accessTokenSecret = $tokenParts['oauth_token_secret'];

		return array(
			'token' => $this->_accessToken,
			'tokenSecret' => $this->_accessTokenSecret
		);
	}

	protected function _http($url, $method = 'GET', $params = null) {

		$headers = array(
			//'Content-type: application/x-www-form-urlencoded'
			'Content-type: multipart/form-data'
		);
		$this->addHeaders($headers, $url, $method, false);

		$opts = [
			'http' => [
				'method' => $method,
				'user_agent' => self::USER_AGENT,
				// follows a 302 redirect just fine
				// but not 301 :-(
				'follow_location' => true,
				'max_redirects'=> 2,
				//'header' => 'Content-type: application/x-www-form-urlencoded',
				'header' => /* implode("\r\n", $headers) */$headers,
			]
		];

		if ($params != null)
		{
			//$postdata = http_build_query(array('data' => $params));
			$postdata = $params;
			$opts['http']['content'] = $postdata ;
		}

		$context = stream_context_create($opts);

		//echo 'url: '.$url."\n";
		//echo 'headers: '.print_r( $headers,true	)."\n";
		//echo 'opts: '.print_r( $opts ,true)."\n";

		$result = @file_get_contents($url, false, $context);
		if ($result === false)
		{
			$e = error_get_last();
			if (isset($http_response_header))
			{
				throw new HttpException($url.' '.print_r($http_response_header,true));
			}
			throw new HttpException($url.' '.$e['message']);
		}

		return $result;
	}

	/**
	 * @see OSM_Auth_IAuthProvider::addHeaders(&$headers, $url, $method)
	 * @param array $headers
	 * @param string $url
	 * @param string $method
	 * @param string $forAccess 
	 */
	public function addHeaders(&$headers, $url, $method = 'GET', $forAccess = true) {

		if ($forAccess)
		{
			$token = $this->_accessToken;
			$secret = $this->_accessTokenSecret;
		}
		else
		{
			$token = $this->_requestToken;
			$secret = $this->_requestTokenSecret;
		}

		$oauth = $this->_prepareParameters($token, $secret, $method, $url);

		$oauthStr = '';
		foreach ($oauth as $name => $value)
		{
			$oauthStr .= $name . '="' . $value . '",';
		}
		$oauthStr = substr($oauthStr, 0, -1); //lose the final ','

		$urlParts = parse_url($url);

		$headers[] = 'Authorization: OAuth realm="' . $urlParts['path'] . '",' . $oauthStr;
	}

	protected function _prepareParameters($token, $secret, $method = null, $url = null) {

		if (empty($method) || empty($url))
			return false;

		$oauth['oauth_consumer_key'] = $this->_consKey;
		$oauth['oauth_token'] = $token;
		$oauth['oauth_nonce'] = md5(uniqid(rand(), true));
		$oauth['oauth_timestamp'] = !isset($this->_timestamp) ? time() : $this->_timestamp;
		$oauth['oauth_signature_method'] = self::SIGNATURE_METHOD;
		$oauth['oauth_version'] = self::PROTOCOL_VERSION;
		if( isset($this->_options['callback_url']))
		{
			$oauth['oauth_callback'] = $this->_options['callback_url'] ;
			if( isset($this->_requestOAuthVerifier))
			{
				$oauth['oauth_verifier'] = $this->_requestOAuthVerifier ;
			}
		}

		// encoding
		array_walk($oauth, array($this, '_encode'));

		// important: does not work without sorting !
		// sign could not be validated by the server
		ksort($oauth);

		// signing
		$oauth['oauth_signature'] = $this->_encode($this->_generateSignature($secret, $method, $url, $oauth));
		return $oauth;
	}

	protected function _generateSignature($secret, $method = null, $url = null, $params = null) {

		if (empty($method) || empty($url))
			return false;

		// concatenating
		$concatenatedParams = '';
		foreach ($params as $k => $v)
		{
			$v = $this->_encode($v);
			$concatenatedParams .= $k . '=' . $v . '&';
		}
		$concatenatedParams = $this->_encode(substr($concatenatedParams, 0, -1));

		$normalizedUrl = $this->_encode($this->_normalizeUrl($url));

		$signatureBaseString = $method . '&' . $normalizedUrl . '&' . $concatenatedParams;
		return $this->_signString($signatureBaseString, $secret);
	}

	/**
	 * Sign the string with the Consumer Secret and the Token Secret.
	 *
	 * @param string $string The string to sign
	 * @return string The signature Base64 encoded
	 */
	protected function _signString($string, $secret) {

		$key = $this->_encode($this->_consSec) . '&' . $this->_encode($secret);
		return base64_encode(hash_hmac('sha1', $string, $key, true));
	}

	protected function _encode($string) {

		return rawurlencode(utf8_encode($string));
	}

	protected function _normalizeUrl( $url )
	{
		$urlParts = parse_url($url);
		$scheme = strtolower($urlParts['scheme']);
		$host = strtolower($urlParts['host']);

		$port = 0;
		if (isset($urlParts['port']))
			$port = intval($urlParts['port']);

		$retval = $scheme . '://' . $host;
		if ($port > 0 /*&& ($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)*/)
		{
			$retval .= ':' . $port;
		}
		$retval .= $urlParts['path'];
		if (!empty($urlParts['query']))
		{
			$retval .= '?' . $urlParts['query'];
		}

		return $retval;
	}

}
