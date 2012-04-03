<?php
/**
 * OSM/Log.php
 */

// http://pear.github.com/Log/
require_once 'Log.php';

/**
 * Class OSM_ZLog
 * A Singleton Log engine.
 * 
 * @author cyrille
 */
class OSM_ZLog {
	const LEVEL_ERROR = PEAR_LOG_ERR;
	const LEVEL_NOTICE = PEAR_LOG_NOTICE;
	const LEVEL_INFO = PEAR_LOG_INFO;
	const LEVEL_DEBUG = PEAR_LOG_DEBUG;

	protected static $_log;
	protected static $_options = array(
		'handler' => 'console',
		'level' => self::LEVEL_NOTICE
	);
	protected static $_isDebug;
	protected static $_isInfo;
	protected static $_isNotice;

	protected static function _getLog() {

		if (is_null(self::$_log))
		{
			self::$_isDebug = false;
			self::$_isInfo = false;
			self::$_isNotice = false;
			switch (self::$_options['level'])
			{
				case self::LEVEL_DEBUG:
					self::$_isDebug = true;
				case self::LEVEL_INFO:
					self::$_isInfo = true;
				case self::LEVEL_NOTICE:
					self::$_isNotice = true;
					break;
			}

			self::$_log = Log::singleton(self::$_options['handler'], '', 'OsmApi', null, self::$_options['level']);
		}

		return self::$_log;
	}

	/**
	 *
	 * @param array $options 
	 * @return Log The new Log instance.
	 */
	public static function configure(array $options) {

		if (is_null($options))
			throw new OSM_Exception('Invalid call of ' . __METHOD__);

		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, self::$_options))
			{
				throw new OSM_Exception('Unknow Log option "' . $k.'"');
			}
			self::$_options[$k] = $v;
		}
		self::$_log = null;
		self::_getLog();
	}

	public static function err($who, $msg='') {

		self::_getLog()->log(self::_makeMessage(func_get_args()), PEAR_LOG_ERR);
	}

	public static function notice($who, $msg='') {
		if (self::$_isNotice)
			self::_getLog()->log(self::_makeMessage(func_get_args()), PEAR_LOG_NOTICE);
	}

	public static function info($who, $msg='') {
		if (self::$_isInfo)
			self::_getLog()->log(self::_makeMessage(func_get_args()), PEAR_LOG_INFO);
	}

	public static function debug($who, $msg='') {
		if (self::$_isDebug)
			self::_getLog()->log(self::_makeMessage(func_get_args()), PEAR_LOG_DEBUG);
	}

	public static function isDebug()
	{
		return self::$_isDebug ;
	}

	protected static function &_makeMessage(array $args) {

		$msg = '[' . $args[0] . '] ';
		$numargs = count($args);
		for ($i = 1; $i < $numargs; $i++)
		{
			$msg.=$args[$i];
		}
		return $msg;
	}

}
