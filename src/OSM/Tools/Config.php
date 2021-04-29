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
        'oauth_url' => OAuth::BASE_URL_DEV,
        'osm_api_url' => OSM_Api::URL_DEV_UK,
        'osm_api_token' => null ,
        'osm_api_secret' => null ,
        'osm_api_consumer_key' => null ,
        'osm_api_consumer_secret' => null ,
        'log_level' => LogLevel::NOTICE,
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