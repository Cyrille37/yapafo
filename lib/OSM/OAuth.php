<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class OSM_OAuth implement OAuth (Open Authorization)
 * 
 * http://oauth.net/documentation/
 * http://tools.ietf.org/html/rfc5849
 * http://wiki.openstreetmap.org/wiki/OAuth
 */
class OSM_OAuth {
	const REQUEST_TOKEN_URL = 'http://www.openstreetmap.org/oauth/request_token';
	const ACCESS_TOKEN_URL = 'http://www.openstreetmap.org/oauth/access_token';
	const AUTHORIZE_TOKEN_URL = 'http://www.openstreetmap.org/oauth/authorize';

	const REQUEST_TOKEN_URL_DEV = 'http://api06.dev.openstreetmap.org/oauth/request_token';
	const ACCESS_TOKEN_URL_DEV = 'http://api06.dev.openstreetmap.org/oauth/access_token';
	const AUTHORIZE_TOKEN_URL_DEV = 'http://api06.dev.openstreetmap.org/oauth/authorize';

	const SIGNATURE_METHOD = 'HMAC-SHA1';

	protected $_reqTokUrl;
	protected $_accTokUrl;
	protected $_authkUrl;
	protected $_consKey;
	protected $_consSec;
	protected $_token;
	protected $_tokenSecret;
	protected $_timestamp;

	public function __construct($consumerKey, $consumerSecret, $devMode=false) {

		$this->_consKey = $consumerKey;
		$this->_consSec = $consumerSecret;

		if ($devMode)
		{
			$this->_reqTokUrl = self::REQUEST_TOKEN_URL_DEV;
			$this->_accTokUrl = self::ACCESS_TOKEN_URL_DEV;
			$this->_authkUrl = self::AUTHORIZE_TOKEN_URL_DEV;
		}
		else
		{
			$this->_reqTokUrl = self::REQUEST_TOKEN_URL;
			$this->_accTokUrl = self::ACCESS_TOKEN_URL;
			$this->_authkUrl = self::AUTHORIZE_TOKEN_URL;
		}
	}

	public function setToken($token, $tokenSecret) {
		$this->_token = $token;
		$this->_tokenSecret = $tokenSecret;
	}

	public function requestAuthorizationUrl() {

		$result = $this->http($this->_reqTokUrl);

		parse_str($result, $tokenParts);
		//echo 'requestAuthorizationUrl: '.print_r( $tokenParts ,true)."\n";

		$this->_token = $tokenParts['oauth_token'];
		$this->_tokenSecret = $tokenParts['oauth_token_secret'];

		return array(
			'url' => $this->_authkUrl . '?oauth_token=' . $this->_token,
			'token' => $this->_token,
			'tokenSecret' => $this->_tokenSecret
		);
	}

	public function requestAccessToken() {

		$result = $this->http($this->_accTokUrl);

		parse_str($result, $tokenParts);
		//echo 'requestAccessToken: '.print_r( $tokenParts ,true)."\n";

		$this->_token = $tokenParts['oauth_token'];
		$this->_tokenSecret = $tokenParts['oauth_token_secret'];

		return array(
			'token' => $this->_token,
			'tokenSecret' => $this->_tokenSecret
		);
	}

	public function http($url, $method ='GET', $params=null) {

		switch ($method)
		{
			case 'GET':
				break;
			case 'POST':
				break;
		}

		if (empty($params['oauth_signature']))
			$params = $this->_prepareParameters($method, $url, $params);

		$urlParts = parse_url($url);

		$oauth = '';
		foreach ($params['oauth'] as $name => $value)
		{
			$oauth .= $name . '="' . $value . '",';
		}
		$oauth = substr($oauth, 0, -1); //lose the final ','

		$headers = array(
			'Expect:',
			'Authorization: OAuth realm="' . $urlParts['path'] /* 'OAuth_essais01' */ . '",' . $oauth,
			'Content-type: application/x-www-form-urlencoded'
		);

		if ($params == null)
		{
			$opts = array('http' =>
				array(
					'method' => $method,
					'user_agent' => 'DEV_OAuth http://www.openstreetmap.org/user/Cyrille37',
					'header' => /* implode("\r\n", $headers) */$headers,
				)
			);
		}
		else
		{
			//$postdata = http_build_query(array('data' => $params));
			$postdata = $params;

			$opts = array('http' =>
				array(
					'method' => $method,
					'user_agent' => 'DEV_OAuth http://www.openstreetmap.org/user/Cyrille37',
					//'header' => 'Content-type: application/x-www-form-urlencoded',
					'header' => /* implode("\r\n", $headers) */$headers,
					'content' => $postdata
				)
			);
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
				throw new OSM_HttpException($http_response_header);
			}
			throw new OSM_HttpException($e['message']);
		}

		return $result;
	}

	protected function _prepareParameters($method = null, $url = null, $params = null) {

		if (empty($method) || empty($url))
			return false;

		$oauth['oauth_consumer_key'] = $this->_consKey;
		$oauth['oauth_token'] = $this->_token;
		$oauth['oauth_nonce'] = md5(uniqid(rand(), true));
		$oauth['oauth_timestamp'] = !isset($this->_timestamp) ? time() : $this->_timestamp;
		$oauth['oauth_signature_method'] = self::SIGNATURE_METHOD;
		$oauth['oauth_version'] = '1.0';

		// encoding
		array_walk($oauth, array($this, '_encode'));
		if (is_array($params))
			array_walk($params, array($this, '_encode'));

		$encodedParams = array_merge($oauth, (array) $params);

		// important: does not work without sorting !
		ksort($encodedParams);

		// signing
		$oauth['oauth_signature'] = $this->_encode($this->_generateSignature($method, $url, $encodedParams));
		return array('request' => $params, 'oauth' => $oauth);
	}

	protected function _generateSignature($method = null, $url = null, $params = null) {

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
		return $this->signString($signatureBaseString);
	}

	/**
	 * Sign the string with the Consumer Secret and the Token Secret.
	 * 
	 * @param type $string The string to sign
	 * @return string The signature Base64 encoded
	 */
	protected function signString($string) {

		$key = $this->_encode($this->_consSec) . '&' . $this->_encode($this->_tokenSecret);
		return base64_encode(hash_hmac('sha1', $string, $key, true));
	}

	protected function _encode($string) {
		return rawurlencode(utf8_encode($string));
	}

	protected function _normalizeUrl($url = null) {
		$urlParts = parse_url($url);
		$scheme = strtolower($urlParts['scheme']);
		$host = strtolower($urlParts['host']);

		$port = 0;
		if (isset($urlParts['port']))
			$port = intval($urlParts['port']);

		$retval = $scheme . '://' . $host;
		if ($port > 0 && ($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))
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
