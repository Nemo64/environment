<?php

namespace Nemo64\Environment\Area;

class ChecksumAreaTest extends AreaInterfaceTest
{
    public function createInstance(string $name = ''): AreaInterface
    {
        return new ChecksumArea($name);
    }

    public function testChange()
    {
        $area = $this->createInstance();
        $handle = fopen('php://temp', 'r+');

        $this->assertFalse($area->exists($handle));
        $area->write($handle, implode("\n", [
            'memory_limit=128M',
            'display_errors=Off',
        ]));

        $this->assertTrue($area->exists($handle));
        $this->assertEquals(
            implode("\n", [
                'memory_limit=128M',
                'display_errors=Off',
            ]),
            $area->read($handle)
        );

        rewind($handle);
        $content = str_replace('Off', 'On', stream_get_contents($handle));
        rewind($handle);
        fwrite($handle, $content);
        ftruncate($handle, strlen($content));

        $this->assertTrue($area->exists($handle));
        $area->write($handle, implode("\n", [
            'memory_limit=256M',
            'display_errors=Off',
            '# an additional line',
        ]));

        $this->assertTrue($area->exists($handle));
        $this->assertEquals(
            implode("\n", [
                'memory_limit=256M',
                'display_errors=On',
                '# an additional line',
            ]),
            $area->read($handle)
        );
    }

    public function testPreviousContent()
    {
        $area = $this->createInstance();
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "test\ntest");

        $area->write($handle, "foo\nfoo");
        $this->assertEquals("test\ntest", $area->read($handle));
    }
}
