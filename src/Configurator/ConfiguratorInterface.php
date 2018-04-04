<?php


namespace Nemo64\Environment\Configurator;


use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;

/**
 * This is the base for every bit of configuration in the environment.
 */
interface ConfiguratorInterface
{
    /**
     * A list of other generators that are configured by this generator.
     * Those other configurators are executed after this one.
     *
     * @return string[]
     */
    public function getInfluences(): array;

    /**
     * This method configures other services and writes to the disk.
     *
     * @param ExecutionContext $context
     * @param ConfiguratorContainer $container
     * @return void
     */
    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void;
}