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

    public static function getBlacklistFromPackage(PackageInterface $package): array
    {
        return $package->getExtra()[EnvironmentBuilder::PACKAGE_NAME]['blacklist'] ?? [];
    }

    public static function getClassesFromRepository(RepositoryInterface $repository): array
    {
        $result = [];

        foreach ($repository->getPackages() as $package) {
            $packageClasses = static::getClassesFromPackage($package);
            foreach ($packageClasses as $packageClass) {
                $class = is_string($packageClass) ? $packageClass : get_class($packageClass);
                $result[$class] = $packageClass;
            }
        }

        foreach ($repository->getPackages() as $package) {
            $packageClasses = static::getBlacklistFromPackage($package);
            foreach ($packageClasses as $packageClass) {
                unset($result[$packageClass]);
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

    public function get(string $class): ?ConfiguratorInterface
    {
        if (!isset($this->instances[$class])) {
            return null;
        }

        return $this->instances[$class];
    }

    private function resolveInfluences(ConfiguratorInterface $configurator): array
    {
        $result = [];

        foreach ($configurator->getInfluences() as $influence) {
            $result[] = $influence;

            if (isset($this->instances[$influence])) {
                $innerResult = $this->resolveInfluences($this->instances[$influence]);
                if ($innerResult) {
                    array_unshift($result, ...$innerResult);
                }
            }
        }

        return $result;
    }

    public function configure(Composer $composer, IOInterface $io, string $rootDir)
    {
        $instances = [];
        foreach ($this->instances as $instance) {
            foreach ($this->resolveInfluences($instance) as $resolvedInfluence) {
                if (isset($this->instances[$resolvedInfluence])) {
                    $instances[$resolvedInfluence] = $this->instances[$resolvedInfluence];
                }
            }
            $instances[get_class($instance)] = $instance;
        }

        $instances = array_reverse($instances);

        $context = new ExecutionContext($composer, $io, $rootDir);
        foreach ($instances as $class => $instance) {
            $io->write("Execute <info>$class</info>", true, IOInterface::VERBOSE);
            $instance->configure($context, $this);
        }
    }
}