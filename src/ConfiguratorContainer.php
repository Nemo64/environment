<?php

namespace Nemo64\Environment;


use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Nemo64\Environment\Configurator\ConfiguratorInterface;

class ConfiguratorContainer
{
    /**
     * @var ConfiguratorInterface[]
     */
    private $instances = [];

    public static function getClassesFromPackage(PackageInterface $package): array
    {
        return $package->getExtra()[EnvironmentBuilder::PACKAGE_NAME]['classes'] ?? [];
    }

    public static function getClassesFromRepository(RepositoryInterface $repository): array
    {
        $result = [];

        foreach ($repository->getPackages() as $package) {
            $packageClasses = static::getClassesFromPackage($package);
            foreach ($packageClasses as $packageClass) {
                $result[] = $packageClass;
            }
        }

        return $result;
    }

    public static function createFromClassList(array $classes): ConfiguratorContainer
    {
        $instances = array_map(function ($class) {
            // if the given class is already an instance just use it
            // this is done during tests...
            return $class instanceof ConfiguratorInterface ? $class : new $class;
        }, $classes);

        return new static($instances);
    }

    public function __construct(array $instances)
    {
        foreach ($instances as $instance) {
            $class = get_class($instance);

            if (isset($this->instances[$class])) {
                $msg = "There are multiple configurators of type $class passed. This is not supported.";
                throw new \RuntimeException($msg);
            }

            $this->instances[$class] = $instance;
        }
    }

    public function configure(Composer $composer, IOInterface $io, string $rootDir)
    {
        usort($this->instances, function (ConfiguratorInterface $a, ConfiguratorInterface $b) {
            if (in_array(get_class($b), $a->getInfluences(), true)) {
                return -1;
            }

            if (in_array(get_class($a), $b->getInfluences(), true)) {
                return 1;
            }

            return 0;
        });

        $context = new ExecutionContext($composer, $io, $rootDir);
        foreach ($this->instances as $instance) {
            $class = get_class($instance);
            $io->write("Execute <info>$class</info>", true, IOInterface::VERBOSE);
            $instance->configure($context);
        }
    }
}