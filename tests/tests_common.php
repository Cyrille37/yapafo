<?php
/**
 * 
 */

class Test_Exception extends Exception 
{
}

/**
 * @param string $str 
 */
function _wl($str) {
	echo $str . "\n";
}

/**
 * @param bool $true 
 */
function _assert( $true )
{
	if( $true !== true )
		throw new Test_Exception('ASSERT FAILED');
}
