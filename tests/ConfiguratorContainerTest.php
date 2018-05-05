<?php

namespace Nemo64\Environment;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Nemo64\Environment\Configurator\ConfiguratorInterface;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class ConfiguratorContainerTest extends TestCase
{
    protected function configure(array $instances)
    {
        $container = new ConfiguratorContainer($instances);
        $composer = new Composer();
        $composer->setPackage(new RootPackage('root/package', '1.0.0', '1.0.0.0'));
        $container->configure($composer, new NullIO(), vfsStream::setup()->url());
    }

    public function testExecute()
    {
        $configurator = $this->createMock(ConfiguratorInterface::class);
        $configurator->method('getInfluences')->willReturn([]);
        $configurator->expects($this->once())->method('configure');
        $this->configure([$configurator]);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testMultipleOfTheSame()
    {
        $c1 = $this->createMock(ConfiguratorInterface::class);
        $this->configure([$c1, $c1]);
    }

    public function testCallOrder()
    {
        $sequence = 0;

        $c1 = $this->getMockBuilder(ConfiguratorInterface::class)->setMockClassName('Class1')->getMock();
        $c1->method('getInfluences')->willReturn([]);
        $c1->expects($this->once())->method('configure')->willReturnCallback(function () use (&$sequence) {
            $this->assertEquals(1, $sequence++, "call order");
        });

        $c2 = $this->getMockBuilder(ConfiguratorInterface::class)->setMockClassName('Class2')->getMock();
        $c2->method('getInfluences')->willReturn([get_class($c1)]);
        $c2->expects($this->once())->method('configure')->willReturnCallback(function () use (&$sequence) {
            $this->assertEquals(0, $sequence++, "call order");
        });

        $this->configure([$c1, $c2]);
    }
}
