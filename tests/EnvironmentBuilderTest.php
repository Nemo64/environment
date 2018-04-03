<?php

namespace Nemo64\Environment;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Nemo64\Environment\Configurator\ConfiguratorInterface;
use PHPUnit\Framework\TestCase;

class EnvironmentBuilderTest extends TestCase
{
    protected function createComposer(PackageInterface ...$packages): Composer
    {
        $composer = new Composer();
        $packages[] = new CompletePackage('noise/package1', '1.0.0', '1.0.0');
        $repositoryManager = $this->createConfiguredMock(RepositoryManager::class, [
            'getLocalRepository' => new ArrayRepository($packages)
        ]);
        $composer->setRepositoryManager($repositoryManager);
        return $composer;
    }

    protected function createComposerWithConfiguration(array $configuration, bool $rootPackage = true, array $packages = []): Composer
    {
        $configuratorPackage = new CompletePackage('configurator/package1', '1.0.0', '1.0.0');
        $configuratorPackage->setExtra([
            'nemo64/environment' => $configuration
        ]);

        if ($rootPackage) {
            $packages[] = $this->createEnvironmentPackage();
        }

        return $this->createComposer($configuratorPackage, ...$packages);
    }

    protected function createEnvironmentPackage(): PackageInterface
    {
        return new CompletePackage(EnvironmentBuilder::PACKAGE_NAME, '1.0.0', '1.0.0');
    }

    public function testExecutionProtection()
    {
        $configurator = $this->createMock(ConfiguratorInterface::class);
        $configurator->expects($this->never())->method('configure');

        $builder = new EnvironmentBuilder();
        $composer = $this->createComposerWithConfiguration(['classes' => [$configurator]], false);
        $builder->execute(new Event('install', $composer, new NullIO()));
    }

    public function testExecute()
    {
        $configurator = $this->createMock(ConfiguratorInterface::class);
        $configurator->expects($this->once())->method('configure');

        $builder = new EnvironmentBuilder();
        $composer = $this->createComposerWithConfiguration(['classes' => [$configurator]]);
        $builder->execute(new Event('install', $composer, new NullIO()));
    }
}
