<?php

namespace Nemo64\Environment\Configurator;

use PHPUnit\Framework\TestCase;

class MakefileConfiguratorTest extends TestCase
{
    use ConfiguratorTestTrait;

    protected function assertMakefileContent(array $expected, string $message = '')
    {
        $expectedString = implode(PHP_EOL, $expected);
        $actualString = trim(file_get_contents($this->rootDir->getChild('Makefile')->url()));
        $this->assertEquals($expectedString, $actualString, $message);
    }

    public function testEmptyMakeFile()
    {
        $makefileConfigurator = new MakefileConfigurator();
        $this->configure($makefileConfigurator);
        $this->assertMakefileContent([
            ".PHONY: help install clean",
            "",
            "help:",
            "",
            "install:",
            "",
            "clean:",
        ]);
    }
}
