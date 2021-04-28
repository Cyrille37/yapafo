<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

use Psr\Log\AbstractLogger ;
use Psr\Log\LogLevel ;

/**
 * A simple PSR-3 Logger Interface implementation.
 * It writes message to STDERR.
 * It can be used as a Singleton or not.
 * 
 * https://www.php-fig.org/psr/psr-3/
 */
class Logger extends AbstractLogger
{
	protected $logLevel ;
	protected static $instance ;

	/**
	 * Undocumented function
	 *
	 * @param string|null $logLevel. null is important for Singleton context usage...
	 * @return \Cyrille37\OSM\Yapafo\Tools\Logger
     * @throws \Psr\Log\InvalidArgumentException
	 */
	public static function getInstance( $logLevel = null )
	{
		if( self::$instance == null )
		{
			self::$instance = new self($logLevel);
		}
		return self::$instance ;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $logLevel
     * @throws \Psr\Log\InvalidArgumentException
	 */
	public function __construct( $logLevel )
	{
		$this->logLevel = $this->computeLogLevel($logLevel);
	}

	protected function interpolate($message, array $context = array())
	{
		// build a replacement array with braces around the context keys
		$replace = array();
		foreach ($context as $key => $val) {
			// check that the value can be cast to string
			if( ! is_array($val) && (!is_object($val) || method_exists($val, '__toString')) )
			{
			}
			else
			{
				$val = print_r($val,true);
			}
			$replace['{' . $key . '}'] = $val;
		}
		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}

	/**
	 * Undocumented function
	 *
	 * @param string $level
	 * @return integer
     * @throws \Psr\Log\InvalidArgumentException
	 */
	protected function computeLogLevel( $logLevel )
	{
		$level = null ;
		switch( $logLevel )
		{
			case LogLevel::EMERGENCY:
			case LogLevel::ALERT:
			case LogLevel::CRITICAL:
			case LogLevel::ERROR:
				$level = -1 ;
				break;
			case LogLevel::WARNING:
				$level = 1 ;
				break;
			case LogLevel::NOTICE:
			case LogLevel::INFO:
				$level = 2 ;
				break;
			case LogLevel::DEBUG:
				$level = 3 ;
				break;
			default:
				throw new \Psr\Log\InvalidArgumentException('Unknow LogLevel "'.$level.'"');
		}
		return $level ;
	}

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log( $level, $message, array $context = array() )
	{
		$logLevel = $this->computeLogLevel($level);
		if( $logLevel > $this->logLevel )
			return ;
		$msg = $this->interpolate($message, $context);
		fwrite(STDERR, '['.strtoupper($level).'] '.$msg."\n");
	}
}
