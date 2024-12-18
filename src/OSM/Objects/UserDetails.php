<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Tools\Logger;

/**
 * Description of UserDetails
 *
 * @author cyrille
 */
class UserDetails
{

	protected $_details =array(
		'id'=>null,
		'account_created'=>null,
		'display_name'=>null,
		'description'=>null,
		'terms'=>array('pd'=>null,'agreed'=>null),
		'home'=>array('lat'=>null,'lon'=>null,'zoom'=>null),
		'img'=>null,
		'languages'=>array()
	);

	public static function createFromXmlString( $xmlStr )
	{
		//OSM_Log::debug(__METHOD__, 'User details: ', $xmlStr);
		$logger = Logger::getInstance();
		$logger->debug(__METHOD__, ['User details: '=> $xmlStr]);

		$x = new \SimpleXMLElement( $xmlStr );

		$userDetails = new UserDetails();

		$u = $x->xpath('/osm/user');
		if( $u== null || count($u)==0)
		{
			throw new OSM_Exception('Error while loading user details');
		}

		$u = $u[0];
		$userDetails->_details['id'] = (string) $u['id'];
		$userDetails->_details['account_created'] = (string) $u['account_created'];
		$userDetails->_details['display_name'] = (string) $u['display_name'];

		$o = $u->xpath('description');
		$userDetails->_details['description'] = $o==null ? '' : (string)$o[0] ;

		$o = $u->xpath('contributor-terms');
		$userDetails->_details['terms']['pd'] = $o==null ? '' : (bool)$o[0]['pd'] ;
		$userDetails->_details['terms']['agreed'] = $o==null ? '' : (bool)$o[0]['agreed'] ;

		$o = $u->xpath('home');
		$userDetails->_details['home']['lat'] = $o==null ? '' : (string)$o[0]['lat'] ;
		$userDetails->_details['home']['lon'] = $o==null ? '' : (string)$o[0]['lon'] ;

		$o = $u->xpath('img');
		$userDetails->_details['img'] = $o==null ? '' : (string)$o[0]['href'] ;

		return $userDetails ;
	}

	/**
	 * Get all user's details.
	 * @return array
	 */
	public function getDetails()
	{
		return $this->_details ;
	}

	public function getId()
	{
		return $this->_details['id'] ;
	}
	public function getName()
	{
		return $this->_details['display_name'] ;
	}
	public function getAccountCreated()
	{
		return $this->_details['account_created'] ;
	}
	public function getDescription()
	{
		return $this->_details['description'] ;
	}
	/**
	 * @return array array('pd'=>bool, 'agreed'=>bool)
	 */
	public function getTerms()
	{
		return $this->_details['terms'] ;
	}
	/**
	 * @return array array('lat'=>number, 'lon'=>number)
	 */
	public function getHome()
	{
		return $this->_details['home'] ;
	}
	public function getImg()
	{
		return $this->_details['img'] ;
	}

}
