<?php

namespace Nemo64\Environment;


use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Repository\WritableRepositoryInterface;
use Webmozart\PathUtil\Path;

class ExecutionContext
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @param string $rootDir
     */
    public function __construct(Composer $composer, IOInterface $io, string $rootDir = '')
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->rootDir = $rootDir;
    }

    /**
     * @return IOInterface
     */
    public function getIo(): IOInterface
    {
        return $this->io;
    }

    /**
     * @return Composer
     * @internal try to use the other methods since it'll make testing a lot easier
     */
    public function getComposer(): Composer
    {
        return $this->composer;
    }

    /**
     * @return WritableRepositoryInterface
     */
    public function getLocalRepository(): WritableRepositoryInterface
    {
        return $this->getComposer()->getRepositoryManager()->getLocalRepository();
    }

    /**
     * @return InstallationManager
     */
    public function getInstallationManager(): InstallationManager
    {
        return $this->getComposer()->getInstallationManager();
    }

    /**
     * @return string
     */
    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * @param string[] ...$path
     * @return string
     */
    public function getPath(string ...$path): string
    {
        return Path::join($this->getRootDir(), ...$path);
    }

    public function info(string $message, bool $newline = true): void
    {
        $this->getIo()->write($message, $newline, IOInterface::VERBOSE);
    }

    public function warn(string $message, bool $newline = true): void
    {
        $this->getIo()->write($message, $newline, IOInterface::NORMAL);
    }
}