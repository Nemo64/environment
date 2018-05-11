<?php

namespace Nemo64\Environment\FileUpdater;


use Composer\IO\IOInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;
use Webmozart\PathUtil\Path;

abstract class AbstractFileUpdaterTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $rootDir;

    public function setUp()
    {
        $this->rootDir = vfsStream::setup('root');
    }

    abstract function createInstance(string $filename, IOInterface $io = null): AbstractFileUpdater;

    public function testCanMerge()
    {
        $instance = $this->createInstance(Path::join($this->rootDir->url(), 'file'));
        $this->assertTrue($instance->canMerge());

        $this->rootDir->addChild(new vfsStreamFile('file'));
        $this->assertFalse($instance->canMerge());
    }

    public function testWrite()
    {
        $instance = $this->createInstance(Path::join($this->rootDir->url(), 'file'));
        $this->assertTrue($instance->canMerge());

        $testContent = "hallo\nworld";
        $instance->write($testContent);
        $this->assertEquals($testContent, $instance->read());
    }
}