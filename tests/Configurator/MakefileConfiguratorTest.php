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
        $actualString = preg_replace("/#[^\n]+\n/m", "", $actualString);
        $this->assertEquals($expectedString, $actualString, $message);
    }

    public function testEmptyMakefile()
    {
        $make = new MakefileConfigurator(false);
        $this->configure($make);
        $this->assertMakefileContent([
            "SHELL=/bin/sh",
            "PHP=php",
        ]);
    }

    public function testEnvironment()
    {
        $make = new MakefileConfigurator(false);
        $make->setEnvironment('Example', "Value");
        $this->configure($make);
        $this->assertMakefileContent([
            "SHELL=/bin/sh",
            "PHP=php",
            "Example=Value",
        ]);
    }

    public function testEnvironmentOverride()
    {
        $make = new MakefileConfigurator(false);

        // default environment variables are a specialty and can be overwritten... but only once
        $make->setEnvironment('PHP', "not-php");
        $make->setEnvironment('PHP', "not-php2");

        // normal values can't be overwritten.
        // This allows other configurators to be executed before another and overwrite their env variable by defining it first
        $make->setEnvironment('VALUE', "1");
        $make->setEnvironment('VALUE', "2");
        $this->configure($make);
        $this->assertMakefileContent([
            "SHELL=/bin/sh",
            "PHP=not-php",
            "VALUE=1",
        ]);
    }

    public function testTarget()
    {
        $make = new MakefileConfigurator(false);
        $make['install']->addCommand('do install');
        $this->configure($make);

        $this->assertMakefileContent([
            "SHELL=/bin/sh",
            "PHP=php",
            "",
            "install:",
            "\tdo install",
        ]);
    }
}
