<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

use Cyrille37\OSM\Yapafo\Auth\OAuth;
use Cyrille37\OSM\Yapafo\OSM_Api ;
use Dotenv\Dotenv ;
use Psr\Log\LogLevel;

/**
 * Singleton to read configuration from .env.
 */
class Config
{
    public $defaults = [
        'simulation' => true,
        'oauth_url' => OAuth::BASE_URL_DEV,
        'osm_api_url' => OSM_Api::URL_DEV_UK,
        'osm_api_url_4write' => OSM_Api::URL_DEV_UK,
        'oapi_url' => OSM_Api::OAPI_URL_DE,
        'xapi_url' => OSM_Api::XAPI_URL_DE,
        'log_level' => LogLevel::NOTICE,

        'osm_api_token' => null ,
        'osm_api_secret' => null ,
        'osm_api_consumer_key' => null ,
        'osm_api_consumer_secret' => null ,
    ];

    protected function __construct( $dir )
    {
        if( ! $dir )
        {
            $dir = __DIR__.'/../../..';
        }
        $dotenv = Dotenv::createImmutable($dir);
        $dotenv->load();
    }

    public static function getInstance( $dir=null )
    {
        static $instance ;
        if( ! $instance )
        {
            $instance = new self( $dir );
        }
        return $instance ;
    }

    public static function get( $key, $default=null)
    {
        $config = self::getInstance();
        if( isset($_ENV[$key]) )
            return $_ENV[$key] ;
        if( $default )
            return $default ;
        if( isset($config->defaults[$key]) )
            return $config->defaults[$key] ;
        return null ;
    }

}