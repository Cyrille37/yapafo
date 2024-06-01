<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

class Ansi
{
	public const EOL = "\n";
	public const TAB = "\t";
	public const ESC = "\033";

	/**
 * Clear all ANSI styling
 */
public const CLOSE = self::ESC . "[0m";

/**
 * Text colours
 */
public const BLACK= self::ESC . "[30m";
public const RED= self::ESC . "[31m";
public const GREEN= self::ESC . "[32m";
public const YELLOW= self::ESC . "[33m";
public const BLUE= self::ESC . "[34m";
public const MAGENTA= self::ESC . "[35m";
public const CYAN= self::ESC . "[36m";
public const WHITE= self::ESC . "[37m";

/**
 * Background colours
 */
public const BACKGROUND_BLACK= self::ESC . "[40m";
public const BACKGROUND_RED= self::ESC . "[41m";
public const BACKGROUND_GREEN= self::ESC . "[42m";
public const BACKGROUND_YELLOW= self::ESC . "[43m";
public const BACKGROUND_BLUE= self::ESC . "[44m";
public const BACKGROUND_MAGENTA= self::ESC . "[45m";
public const BACKGROUND_CYAN= self::ESC . "[46m";
public const BACKGROUND_WHITE= self::ESC . "[47m";

/**
 * Text styles
 */
public const BOLD= self::ESC . "[1m";
public const ITALIC= self::ESC . "[3m"; // limited support.
public const UNDERLINE= self::ESC . "[4m";
public const STRIKETHROUGH= self::ESC . "[9m";

}