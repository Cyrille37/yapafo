<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 *
 * @author cyrille
 */
interface OSM_Objects_IDirty {

	/**
	 * @return bool
	 */
	public function isDirty();
	public function setDirty($dirty=true);

}

