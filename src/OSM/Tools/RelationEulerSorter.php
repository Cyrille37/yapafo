<?php

use Cyrille37\OSM\Yapafo\Objects\Relation;

class RelationEulerHierholzer
{

/**
 * Sort Relation's members using Euler/Hierholzer algorithm.
 * 
 * If sort success, members are sorted and the Relation is set as "dirty".
 * 
 * If sort failed, a SortException is throwed and the Relation is not modified.
 * SortException messages:
 * - "Missing ways"
 * - "Duplicate ways"
 * 
 * @param Relation $relation 
 * @return void 
 */
  public static function sort(Relation $relation) {}
}
