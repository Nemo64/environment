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

    public function __construct(bool $init = true)
    {
        // used for testing
        if (!$init) {
            return;
        }

        // add basic task structure
        $this['.PHONY']->setPriority(800);
        $this['.PHONY']->addDependency($this['help']);
        $this['.PHONY']->addDependency($this['install']);
        $this['.PHONY']->addDependency($this['clean']);

        // describe basic tasks
        $this['help']->setPriority(1000);
        $this['help']->setDescription("Prints this help text.");
        $this['install']->setDescription("Installs all dependencies of the project.");
        $this['clean']->setDescription("Removes dependencies and temporary files to get a clean start. Hint: make clean install");
    }

    public function getEnvironment(): array
    {
        // the environment variables SHELL and PHP are there by default.
        // they can be overwritten but only once. This is implemented by simply not defining them until needed.
        return array_replace([
            'SHELL' => '/bin/sh',
            'PHP' => 'php',
        ], $this->environment);
    }

    public function getInfluences(): array
    {
        return [];
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        uasort($this->targets, function (Target $a, Target $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
        $this->generateHelpTarget();
        $this->writeFile($context);
    }

    protected function generateHelpTarget(): void
    {
        foreach ($this['.PHONY']->getDependencies() as $target) {
            $descriptionLines = explode(PHP_EOL, $target->getDescription());
            $this['help']->addCommand(sprintf('$(info - %10s: %s)', $target->getName(), reset($descriptionLines)));
            foreach (array_slice($descriptionLines, 1) as $descriptionLine) {
                $this['help']->addCommand(sprintf('$(info %s)', "\t" . $descriptionLine));
            }
        }
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

    /**
     * @param ExecutionContext $context
     */
    protected function writeFile(ExecutionContext $context): void
    {
        $result = [];

        foreach ($this->getEnvironment() as $key => $value) {
            $result[] = "$key=$value";
        }

        foreach ($this->targets as $target) {
            if ($target->isEmpty()) {
                continue;
            }

            $result[] = PHP_EOL . $target->__toString();
        }

        file_put_contents(
            $context->getPath('Makefile'),
            implode("\n", $result) . "\n"
        );
        $context->getIo()->write("Makefile rewritten.", true, IOInterface::VERBOSE);
    }
}