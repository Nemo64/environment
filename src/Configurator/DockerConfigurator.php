<?php

namespace Nemo64\Environment\Configurator;


use Nemo64\Environment\Area\ChecksumArea;
use Nemo64\Environment\Configurator\Make\Target;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;
use Symfony\Component\Yaml\Yaml;

class DockerConfigurator implements ConfiguratorInterface
{
    private $services = [];
    private $volumes = [];
    private $dockerfiles = [];

    public function getInfluences(): array
    {
        return [
            MakefileConfigurator::class,
            GitignoreConfigurator::class
        ];
    }

    public function defineService(string $name, array $definition): void
    {
        $this->services[$name] = array_merge_recursive($this->services[$name] ?? [], $definition);
    }

    public function defineVolume(string $name, array $definition = []): void
    {
        $this->volumes[$name] = array_merge_recursive($this->services[$name] ?? [], $definition);
    }

    public function createDockerfile(string $name, array $lines): void
    {
        if (isset($this->dockerfiles[$name])) {
            throw new \LogicException("Dockerfile $name is already created");
        }

        $this->dockerfiles[$name] = $lines;
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $area = new ChecksumArea();

        $area->write(
            fopen($context->getPath(".dockerignore"), 'c+'),
            '*'
        );

        foreach ($this->dockerfiles as $name => $dockerfile) {
            $area->write(
                fopen($context->getPath($name), 'c+'),
                implode("\n", $dockerfile)
            );
        }

        $dockerComposeContent = [
            'version' => '3',
            'services' => $this->services,
            'volumes' => $this->volumes
        ];
        $area->write(
            fopen($context->getPath('docker-compose.yml'), 'c+'),
            Yaml::dump($dockerComposeContent, 4, 2)
        );

        $make = $container->get(MakefileConfigurator::class);
        if ($make !== null) {
            $make['install']->addDependency($make['docker-compose.log']);
            $make['docker-compose.log']->addCommand('docker-compose build |tee docker-compose.log');
            foreach ($this->dockerfiles as $name => $content) {
                $make['docker-compose.log']->addDependencyString($name);
            }

            $gitignore = $container->get(GitignoreConfigurator::class);
            if ($gitignore) {
                $gitignore->add('docker-compose.log');
            }

            $make['.PHONY']->addDependency($make['start']);
            $make['start']->setDescription("Start all services.");
            $make['start']->addDependency($make['install']);
            $make['start']->addCommand('docker-compose up --detach');

            $make['.PHONY']->addDependency($make['stop']);
            $make['stop']->setDescription("Stop all services.");
            $make['stop']->addCommand('docker-compose down --remove-orphans');
        }
    }
}