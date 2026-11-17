<?php

namespace Cyrille37\OSM\Yapafo\Tests\Integration;

use Cyrille37\OSM\Yapafo\OSM_Api;
use Psr\Log\LogLevel;

class RelationTest extends TestCase
{

  public function testLoad()
  {
    $relation_id = 17380484;
    $osmapi = new OSM_Api([
      'log' => [
        'level' => LogLevel::INFO
      ]
    ]);
    $relation = $osmapi->getRelation($relation_id);
    $members = $relation->getMembers();
    echo 'members: ', count($members),"\n";
    foreach( $members as $member)
      {
        //echo $member->getRef(),"\n";
      }
  }
}
