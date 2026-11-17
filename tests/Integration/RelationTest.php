<?php

namespace Cyrille37\OSM\Yapafo\Tests\Integration;

use Cyrille37\OSM\Yapafo\Objects\OSM_Object;
use Cyrille37\OSM\Yapafo\OSM_Api;
use Psr\Log\LogLevel;

class RelationTest extends TestCase
{

  public function testLoad()
  {
    $relation_id = 17380484;
    $osmapi = new OSM_Api([
      'log' => [
        'level' => LogLevel::NOTICE
      ]
    ]);
    // 📢 don't use `$full=false` because of too much OSM API calls !
    $relation = $osmapi->getRelation($relation_id, $full = true);
    $members = $relation->getMembers();
    $stats = [
      'members' => count($members),
      'ways' => 0,
      'nodes' => 0,
    ];
    foreach ($members as $member) {
      //echo $member->getRef(),"\n";
      switch ($member->getType()) {
        case OSM_Object::OBJTYPE_WAY:
          $way = $osmapi->getWay($member->getRef());
          if ($way) {
            $stats['ways']++;
            foreach ($way->getNodesRefs() as $nodeRef) {
              $node = $osmapi->getNode($nodeRef);
              if ($node)
                $stats['nodes']++;
            }
          }
          break;
        case OSM_Object::OBJTYPE_NODE:
          $node = $osmapi->getNode($member->getRef());
          if ($node)
            $stats['nodes']++;
          break;
        default:
      }
    }
    echo "\nSTATS: ", var_export($stats, true), "\n";
  }
}
