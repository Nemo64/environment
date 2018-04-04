<?php

namespace Nemo64\Environment\Configurator;


use Composer\Composer;
use Composer\Config;
use Composer\IO\NullIO;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

trait ConfiguratorTestTrait
{
    /**
     * @var vfsStreamDirectory
     */
    protected $rootDir;

    public function setUp()
    {
        $this->rootDir = vfsStream::setup('root');
    }

    protected function createContext(array $packages = []): ExecutionContext
    {
        try {
            $localRepository = $this->createMock(WritableRepositoryInterface::class);
            $localRepository->method('getCanonicalPackages')->willReturn($packages);

            $repositoryManager = $this->createMock(RepositoryManager::class);
            $repositoryManager->method('getLocalRepository')->willReturn($localRepository);

            $composer = $this->createMock(Composer::class);
            $composer->method('getRepositoryManager')->willReturn($repositoryManager);
            $composer->method('getConfig')->willReturn(new Config(false, $this->rootDir->url()));

            return new ExecutionContext($composer, new NullIO(), $this->rootDir->url());
        } catch (\ReflectionException $e) {
            throw new \RuntimeException('could not create context', 0, $e);
        }
    }

    protected function configure(ConfiguratorInterface $configurator)
    {
        $container = new ConfiguratorContainer([$configurator]);
        $configurator->configure($this->createContext(), $container);
    }
}