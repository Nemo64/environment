<?php

namespace Nemo64\Environment\Area;


use PHPUnit\Framework\TestCase;

abstract class AreaInterfaceTest extends TestCase
{
    public abstract function createInstance(): AreaInterface;

//    public function testStreamCopy()
//    {
//        $handle = fopen('php://temp', 'rw');
//        $string = 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor.';
//        fwrite($handle, $string);
//        $this->assertEquals(strlen($string), ftell($handle), 'handle at the end of the file');
//        fseek($handle, 5);
//        stream_copy_to_stream($handle, $handle, null, -5);
//        fseek($handle, 0);
//        $this->assertEquals(
//            substr($string, 0, 5) . $string,
//            fread($handle, strlen($string) + 5),
//            'copied without corruption'
//        );
//    }

    public function testWrite()
    {
        $handle = fopen('php://temp', 'r+');
        $area = $this->createInstance();
        $this->assertFalse($area->exists($handle), "Area empty.");
        $area->write($handle, 'testWrite');
        $this->assertTrue($area->exists($handle), "Area not empty anymore.");
        $this->assertEquals('testWrite', $area->read($handle), "Area content correct");
        $area->write($handle, 'longerTestWrite');
        $this->assertEquals('longerTestWrite', $area->read($handle));
        $area->write($handle, 'shortWrite');
        $this->assertEquals('shortWrite', $area->read($handle));
    }

    public function testWriteExistingFile()
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "This\nis\ntext.");

        $area = $this->createInstance();
        $this->assertFalse($area->exists($handle), "Area empty.");
        $area->write($handle, 'testWrite');
        $this->assertTrue($area->exists($handle), "Area not empty anymore.");
        $this->assertEquals('testWrite', $area->read($handle), "Area content correct");

        $this->assertContains("This\nis\ntext.", stream_get_contents($handle, -1, 0));
    }
}