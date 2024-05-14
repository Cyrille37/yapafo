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
    protected function __construct( $dir )
    {
        if( ! $dir )
        {
            $dir = __DIR__.'/../../..';
        }
        $dotenv = Dotenv::create($dir);
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
        if( $default !== null )
            return $default ;
        return null ;
    }

}