<?php

namespace Nemo64\Environment\Area;


use PHPUnit\Framework\TestCase;

abstract class AreaInterfaceTest extends TestCase
{
    public abstract function createInstance(string $name): AreaInterface;

    public function testWrite()
    {
        $handle = fopen('php://temp', 'r+');
        $area = $this->createInstance('area1');
        $this->assertFalse($area->exists($handle), "Area empty.");
        $area->write($handle, 'testWrite');
        $this->assertTrue($area->exists($handle), "Area not empty anymore.");
        $this->assertEquals('testWrite', $area->read($handle), "Area content correct");
        $area->write($handle, 'longerTestWrite');
        $this->assertEquals('longerTestWrite', $area->read($handle));
        $area->write($handle, 'shortWrite');
        $this->assertEquals('shortWrite', $area->read($handle));
    }
}