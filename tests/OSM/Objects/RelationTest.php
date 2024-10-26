<?php

namespace Cyrille37\OSM\Yapafo\Objects;

use Cyrille37\OSM\Yapafo\TestCase;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception;

class RelationTest extends TestCase
{

    public function testAddMember()
    {
        $relation = new Relation(1);
        $this->assertNotNull($relation);
        $m1 = new Member(OSM_Object::OBJTYPE_NODE, 123);
        $m2 = new Member(OSM_Object::OBJTYPE_NODE, 124);
        $relation->addMember($m1);
        $relation->addMember($m2);

        $m = $relation->getMember(OSM_Object::OBJTYPE_NODE, 123);
        $this->assertEquals(OSM_Object::OBJTYPE_NODE, $m->getType());
    }

    public function testAddMemberDuplicate()
    {
        $this->expectException(OSM_Exception::class);
        $relation = new Relation(1);
        $this->assertNotNull($relation);
        $m1 = new Member(OSM_Object::OBJTYPE_NODE, 123);
        $m2 = new Member(OSM_Object::OBJTYPE_NODE, 123);
        $relation->addMember($m1);
        $relation->addMember($m2);
    }

    public function testAddMemberDuplicateButDifferentRole()
    {
        $relation = new Relation(1);
        $this->assertNotNull($relation);
        $m1 = new Member(OSM_Object::OBJTYPE_NODE, 123, 'forward');
        $m2 = new Member(OSM_Object::OBJTYPE_NODE, 123, 'backward');
        $relation->addMember($m1);
        $relation->addMember($m2);

        // Default return the first object
        $m = $relation->getMember(OSM_Object::OBJTYPE_NODE, 123);
        $this->assertEquals(OSM_Object::OBJTYPE_NODE, $m->getType());

        $m = $relation->getMember(OSM_Object::OBJTYPE_NODE, 123, false);
        $this->assertTrue(is_array($m));
        $this->assertEquals(2, count($m));
    }
}
