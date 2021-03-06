<?php

namespace Nemo64\Environment\FileUpdater;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Webmozart\PathUtil\Path;

class ChecksumFileUpdaterTest extends AbstractFileUpdaterTest
{
    /**
     * @param string $filename
     * @param IOInterface|null $io
     * @return ChecksumFileUpdater
     */
    public function createInstance(string $filename, IOInterface $io = null): AbstractFileUpdater
    {
        return new ChecksumFileUpdater($io ?? new NullIO(), $filename);
    }

    public function testChange()
    {
        $filename = Path::join($this->rootDir->url(), 'file');
        $instance = $this->createInstance($filename);

        $this->assertTrue($instance->canMerge());
        $this->assertTrue($instance->write(implode("\n", [
            'memory_limit=128M',
            'display_errors=Off',
        ])));

        $this->assertTrue($instance->canMerge());
        $this->assertEquals(
            implode("\n", [
                'memory_limit=128M',
                'display_errors=Off',
            ]),
            $instance->read()
        );

        file_put_contents($filename, str_replace('Off', 'On', file_get_contents($filename)));

        $this->assertTrue($instance->canMerge());
        $this->assertTrue($instance->write(implode("\n", [
            'memory_limit=256M',
            'display_errors=Off',
            '# an additional line',
        ])));

        $this->assertTrue($instance->canMerge());
        $this->assertEquals(
            implode("\n", [
                'memory_limit=256M',
                'display_errors=On',
                '# an additional line',
            ]),
            $instance->read()
        );
    }

    public function testPreviousContent()
    {
        $filename = Path::join($this->rootDir->url(), 'file');
        $area = $this->createInstance($filename);
        file_put_contents($filename, "test\ntest");

        $this->assertFalse($area->write("foo\nfoo"));
        $this->assertEquals("test\ntest", $area->read());
    }

    public static function conflictDataProvider()
    {
        return [
            ['a', "user content", "environment content", "user content\nenvironment content"],
            ['m', "line1\nline2", "line2", "line1\nline2"],
            ['i', "line1\nline2", "line3", "line1\nline2"],
        ];
    }

    /**
     * @dataProvider conflictDataProvider
     */
    public function testConflict($method, $userContent, $envContent, $expectedResult)
    {
        $filename = Path::join($this->rootDir->url(), 'file');
        file_put_contents($filename, $userContent);

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('select')->willReturn($method);
        $updater = $this->createInstance($filename, $io);
        $this->assertFalse($updater->canMerge());

        $updater->write($envContent);
        $this->assertEquals(
            $updater->getChecksumComment($envContent) . "\n$expectedResult",
            file_get_contents($filename)
        );
    }
}
