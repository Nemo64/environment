<?php

namespace Nemo64\Environment\Area;

class ChecksumAreaTest extends AreaInterfaceTest
{
    public function createInstance(): AreaInterface
    {
        return new ChecksumArea('test area');
    }

    public function testChange()
    {
        $area = $this->createInstance();
        $handle = fopen('php://temp', 'r+');

        $this->assertFalse($area->exists($handle));
        $area->write($handle, implode(PHP_EOL, [
            'memory_limit=128M',
            'display_errors=Off',
        ]));

        $this->assertTrue($area->exists($handle));
        $this->assertEquals(
            implode(PHP_EOL, [
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
        $area->write($handle, implode(PHP_EOL, [
            'memory_limit=256M',
            'display_errors=Off',
            '# an additional line',
        ]));

        $this->assertTrue($area->exists($handle));
        $this->assertEquals(
            implode(PHP_EOL, [
                'memory_limit=256M',
                'display_errors=On',
                '# an additional line',
            ]),
            $area->read($handle)
        );
    }
}
