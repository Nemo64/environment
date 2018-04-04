<?php

namespace Nemo64\Environment\Configurator;


use Composer\IO\IOInterface;
use Nemo64\Environment\Configurator\Make\EnvironmentContainer;
use Nemo64\Environment\Configurator\Make\Target;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;

class MakefileConfigurator implements ConfiguratorInterface, \ArrayAccess
{
    use EnvironmentContainer;

    /**
     * @var Target[]
     */
    private $targets = [];

    public function __construct()
    {
        // add basic task structure
        $this['.PHONY']->addDependency($this['help']);
        $this['.PHONY']->addDependency($this['install']);
        $this['.PHONY']->addDependency($this['clean']);
    }

    public function getInfluences(): array
    {
        return [];
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $result = [];

        foreach ($this->getEnvironment() as $key => $value) {
            $result[] = "$key=$value";
        }

        foreach ($this->targets as $target) {
            $result[] = PHP_EOL . $target->__toString();
        }

        file_put_contents(
            $context->getPath('Makefile'),
            implode("\n", $result) . "\n"
        );
        $context->getIo()->write("Makefile rewritten.", true, IOInterface::VERBOSE);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->targets[$offset]);
    }

    public function offsetGet($offset): Target
    {
        if (!isset($this->targets[$offset])) {
            $this->targets[$offset] = new Target($offset);
        }

        return $this->targets[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof Target) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("Expected Target, got $type");
        }

        $this->targets[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->targets[$offset]);
    }
}