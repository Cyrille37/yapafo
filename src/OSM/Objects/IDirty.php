<?php
namespace Cyrille37\OSM\Yapafo\Objects ;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 *
 * @author cyrille
 */
interface IDirty {

	/**
	 * @return bool
	 */
	public function isDirty();
	public function setDirty($dirty=true);

}

