<?php


namespace Nemo64\Environment\Configurator;


use Nemo64\Environment\ExecutionContext;
use Symfony\Component\OptionsResolver\OptionsResolver;

interface ConfigurableConfiguratorInterface extends ConfiguratorInterface
{
    public function configureOptions(ExecutionContext $context, OptionsResolver $resolver): void;
}