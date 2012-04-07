<?php

/**
 * OSM/Exception.php
 */

/**
 * Description of OSM_Exception
 *
 * @author cyrille
 */
class OSM_HttpException extends OSM_Exception {

	public $http_response_header;

	public function __construct($http_response_headerOrErrorMessage) {
		parent::__construct();
		
		if(is_array($http_response_headerOrErrorMessage))
		{
			$this->http_response_header = $http_response_headerOrErrorMessage;
			$this->message = $this->getHttpRaison();
		}
		else
		{
			$this->http_response_header = null ;
			$this->message = $http_response_headerOrErrorMessage;
		}
	}

	public function getHttpRaison() {
		
		if( $this->http_response_header == null )
		{
			return $this->message ;
		}

		if( ! isset($this->http_response_header[0]) )
		{
			return implode(',', $this->http_response_header );
		}
		return $this->http_response_header[0];
	}

	public function getHttpCode() {
		if( $this->http_response_header == null )
		{
			return 0 ;
		}
		$parts = explode(' ', $this->http_response_header[0]);
		return $parts[1];
	}

	public function getApiError() {

		if( $this->http_response_header == null )
		{
			return $this->message ;
		}

		/*
		  [0] => HTTP/1.1 400 Bad Request
		  [1] => Date: Tue, 17 Jan 2012 10:05:08 GMT
		  [2] => Server: Apache/2.2.14 (Ubuntu)
		  [3] => X-Powered-By: Phusion Passenger (mod_rails/mod_rack) 3.0.11
		  [4] => X-UA-Compatible: IE=Edge,chrome=1
		  [5] => X-Runtime: 0.013857
		  [6] => Cache-Control: no-cache
		  [7] => Error: Element relation/10 has duplicate tags with key toto
		  [8] => Status: 400
		  [9] => Vary: Accept-Encoding
		  [10] => Content-Length: 52
		  [11] => Connection: close
		  [12] => Content-Type: text/html; charset=utf-8

		  [0] => HTTP/1.1 401 Authorization Required
		  [1] => Date: Thu, 19 Jan 2012 07:33:28 GMT
		  [2] => Server: Apache/2.2.14 (Ubuntu)
		  [3] => X-Powered-By: Phusion Passenger (mod_rails/mod_rack) 3.0.11
		  [4] => WWW-Authenticate: Basic realm="Web Password"
		  [5] => X-UA-Compatible: IE=Edge,chrome=1
		  [6] => X-Runtime: 0.033963
		  [7] => Cache-Control: no-cache
		  [8] => Status: 401
		  [9] => Vary: Accept-Encoding
		  [10] => Content-Length: 25
		  [11] => Connection: close
		  [12] => Content-Type: text/html; charset=utf-8
		 */

		foreach ($this->http_response_header as $h)
		{
			if (strpos($h, 'Error:') === 0)
				return $h;
		}
		return $this->getHttpRaison();
	}

}
