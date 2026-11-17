<?php

namespace Cyrille37\OSM\Yapafo\Tests\Unit\OSM\Tools;

use Cyrille37\OSM\Yapafo\Exceptions\SortException;
use Cyrille37\OSM\Yapafo\Objects\Node;
use Cyrille37\OSM\Yapafo\Objects\Relation;
use Cyrille37\OSM\Yapafo\Objects\Way;
use Cyrille37\OSM\Yapafo\Tools\RelationEulerSorter;
use Cyrille37\OSM\Yapafo\Tests\Unit\TestCase;

class RelationEulerSorterTest extends TestCase
{
  /**
   * Route with a queue (2 odd nodes): the BFS path between them is doubled
   * and a closed Eulerian circuit is produced.
   */
  public function testOk(): void
  {
    [$relation, $ways, $nodes] = self::loadFixture('rel_17380484.xml');

    RelationEulerSorter::sort($relation, $ways, $nodes);

    $this->assertTrue($relation->isDirty());

    $expected = [
      476064738, 134124241, 502493487, 1265787885, 258352185, 612274978,
      362655666, 749290482, 134124228, 749290481, 1265846570, 438299540,
      438299539, 134124250, 134124249, 1265787884, 134124212, 763098835,
      134124246, 1577806942, 1265787883, 415262407, 456856436, 1265787882,
      763098834, 1090462555, 763098838, 492087202, 134124226, 367135464,
      764307047, 729377071, 41220249, 1265787876, 134509942, 1265787874,
      1180378786, 1180378787, 1180378783, 559411322, 1484205805, 559411321,
      710704241, 710704242, 1331402478, 1265787871, 456856444, 1265787890,
      501163369, 501163368, 329670579, 38867456, 1265787891, 751269834,
      134124209, 405261994, 329689018, 24276097, 1265787894, 362799051,
      1265787895, 362799045, 1265787896, 362799043, 362799042, 1265787897,
      362799058, 754262482, 1265787898, 772404134, 362670044, 1265787901,
      128918643, 134630892, 1265787887, 134124241, 476064738,
    ];

    $actual = array_map(fn($m) => (int) $m->getRef(), $relation->getMembers());

    $this->assertSame($expected, $actual);
  }

  /**
   * Hole in the loop: way 749290481 removed from the relation.
   * 4 odd nodes remain, no edge links two odd nodes → ERR_MISSING_WAYS.
   */
  public function testWithHole(): void
  {
    [$relation, $ways, $nodes] = self::loadFixture('rel_17380484_err01.xml');

    $this->expectException(SortException::class);
    $this->expectExceptionCode(SortException::ERR_MISSING_WAYS);

    RelationEulerSorter::sort($relation, $ways, $nodes);
  }

  /**
   * Way duplicated by mistake: way 749290481 appears twice.
   * 4 odd nodes remain, an edge links two odd nodes → ERR_DUPLICATE_WAYS.
   */
  public function testWithDuplicate(): void
  {
    $_ENV[Relation::OPTION_OSM_RELATION_DUPLICATE_AUTHORISED] = '1';
    try {
      [$relation, $ways, $nodes] = self::loadFixture('rel_17380484_err02.xml');
    } finally {
      unset($_ENV[Relation::OPTION_OSM_RELATION_DUPLICATE_AUTHORISED]);
    }

    $this->expectException(SortException::class);
    $this->expectExceptionCode(SortException::ERR_DUPLICATE_WAYS);

    RelationEulerSorter::sort($relation, $ways, $nodes);
  }

  /**
   * Parse an OSM XML fixture into (Relation, Way[], Node[]).
   *
   * @param string $file Fixture file name inside tests/fixtures/
   * @return array{0: Relation, 1: array<int, Way>, 2: array<int, Node>}
   */
  private static function loadFixture(string $file): array
  {
    $xml = simplexml_load_file(__DIR__ . '/../../../fixtures/' . $file);
    self::assertNotNull($xml, 'fixture "' . $file . '" not found or not valid XML');

    $ways = [];
    $nodes = [];
    $relation = null;

    foreach ($xml->children() as $child) {
      switch ($child->getName()) {
        case 'node':
          $node = Node::fromXmlObj($child);
          $nodes[(int) $node->getId()] = $node;
          break;
        case 'way':
          $way = Way::fromXmlObj($child);
          $ways[(int) $way->getId()] = $way;
          break;
        case 'relation':
          $relation = Relation::fromXmlObj($child);
          break;
      }
    }

    self::assertNotNull($relation, 'no <relation> in fixture "' . $file . '"');

    return [$relation, $ways, $nodes];
  }
}
