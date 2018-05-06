<?php

namespace Nemo64\Environment;


use Composer\Composer;
use Composer\Config\JsonConfigSource;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Nemo64\Environment\Configurator\ConfigurableConfiguratorInterface;
use Nemo64\Environment\Configurator\ConfiguratorInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfiguratorContainer
{
    /**
     * @var ConfiguratorInterface[]
     */
    private $instances = [];

    /**
     * @var array|null
     */
    private $options = null;

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

    public function getOption(string $name)
    {
        if ($this->options === null) {
            throw new \LogicException("Options aren't available yet.");
        }

        return $this->options[$name] ?? null;
    }

    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
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

        // sort instances so that the influenced instances are in the list first
        foreach ($this->instances as $instance) {
            foreach ($this->resolveInfluences($instance) as $resolvedInfluence) {
                if (isset($this->instances[$resolvedInfluence])) {
                    $instances[$resolvedInfluence] = $this->instances[$resolvedInfluence];
                }
            }
            $instances[get_class($instance)] = $instance;
        }

        $context = new ExecutionContext($composer, $io, $rootDir);
        $optionResolver = $this->createOptionResolver($context);

        foreach ($instances as $instance) {
            if ($instance instanceof ConfigurableConfiguratorInterface) {
                $instance->configureOptions($context, $optionResolver);
            }
        }

        // reverse the order so that the influencers are executed first
        /** @var ConfiguratorInterface[] $instances */
        $instances = array_reverse($instances);

        // resolve options
        $oldExtra = $extra = $composer->getPackage()->getExtra();
        $this->options = $optionResolver->resolve($extra[EnvironmentBuilder::PACKAGE_NAME]['options'] ?? []);

        // run configurators
        foreach ($instances as $class => $instance) {
            $io->write("Execute <info>$class</info>", true, IOInterface::VERBOSE);
            $instance->configure($context, $this);
        }

        // persist options
        $extra[EnvironmentBuilder::PACKAGE_NAME]['options'] = $this->options;
        $composer->getPackage()->setExtra($extra);
        if ($extra != $oldExtra && $io->isInteractive()) {
            $io->write("options have changed, write compose.json extra section");
            $composerFilePath = trim(getenv('COMPOSER')) ?: $context->getPath('composer.json');
            $composerFile = new JsonFile($composerFilePath, null, $context->getIo());
            $configSource = new JsonConfigSource($composerFile);
            $configSource->addProperty('extra', $extra);
        }
    }

    protected function createOptionResolver(ExecutionContext $context): OptionsResolver
    {
        $optionResolver = new OptionsResolver();

        $optionResolver->setDefault('document-root', function (Options $options) use ($context) {
            return $context->getIo()->ask("Define your document root (default 'public'): ", 'public');
        });
        $optionResolver->setNormalizer('document-root', function (Options $options, $documentRoot) {
            return trim($documentRoot, '/');
        });

        return $optionResolver;
    }
}