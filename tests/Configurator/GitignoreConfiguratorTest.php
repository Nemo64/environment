<?php

namespace Nemo64\Environment\Configurator;

use Composer\Composer;
use Composer\Config;
use Composer\IO\NullIO;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Nemo64\Environment\ExecutionContext;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;

class GitignoreConfiguratorTest extends TestCase
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

    protected function createConfigurationWith(string ...$rules): GitignoreConfigurator
    {
        $gitignoreConfigurator = new GitignoreConfigurator();
        $gitignoreConfigurator->setAddLocalPackages(false);
        foreach ($rules as $rule) {
            $gitignoreConfigurator->add($rule);
        }

        return $gitignoreConfigurator;
    }

    protected function createAndUseConfigurationWith(string ...$rules): void
    {
        $this->createConfigurationWith(...$rules)->configure($this->createContext());
    }

    protected function createFileWith(string ...$rules): vfsStreamFile
    {
        $file = new vfsStreamFile('.gitignore');
        $this->rootDir->addChild($file);
        file_put_contents($file->url(), implode(PHP_EOL, $rules));
        // this implementation leaves the tailing slash away on purpose
        return $file;
    }

    protected function assertGitignoreContent(array $expected, string $message = '')
    {
        $content = file_get_contents($this->rootDir->getChild('.gitignore')->url());
        $actual = array_filter(explode(PHP_EOL, $content), 'strlen');
        $this->assertEquals($actual, $expected, $message);
    }

    public function testCreateGitignore()
    {
        $gitignoreConfigurator = $this->createConfigurationWith();
        $this->assertFalse($this->rootDir->hasChildren(), "Noting done yet");

        $gitignoreConfigurator->configure($this->createContext());
        $this->assertFalse($this->rootDir->hasChildren(), "Still no gitignore created since it would be empty");

        $this->createAndUseConfigurationWith('/vendor');
        $this->assertGitignoreContent(['/vendor'], "file exists with singe rule");
    }

    public function testMergingRules()
    {
        $this->createFileWith('folder1', 'folder2');
        $this->createAndUseConfigurationWith('folder2', 'folder3');
        $this->assertGitignoreContent([
            'folder1', // from existing file
            'folder2', // merged
            'folder3', // from configuration
        ]);
    }

    public function testOptimizeRules()
    {
        $this->createFileWith('folder1', 'folder2/sub');
        $this->createAndUseConfigurationWith('folder1/sub', 'folder2');
        $this->assertGitignoreContent([
            'folder1', // from existing file merged with folder1/sub
            'folder2/sub', // keeped from existing file since it doesn't harm
            'folder2', // added from configuration anyway
        ]);
    }

    public function testAbsoluteAndRelative()
    {
        $this->createFileWith('/folder1', 'folder2');
        $this->createAndUseConfigurationWith('folder1', '/folder2');
        $this->assertGitignoreContent([
            '/folder1', // preserved
            'folder2', // preserved and merged
            'folder1', // from configuration
            '/folder2' // /folder2 is already covered by folder2 ... but the check wasn't elegant enough so i didn't bother
        ]);
    }

    public function testRespectComments()
    {
        $this->createFileWith('#/folder1');
        $this->createAndUseConfigurationWith('/folder1');
        $this->assertGitignoreContent(['#/folder1']);
    }

    public function testRespectKeep()
    {
        $this->createFileWith('!/folder1');
        $this->createAndUseConfigurationWith('/folder1');
        $this->assertGitignoreContent(['!/folder1']);
    }

    public function testCreatePreserveComment()
    {
        $this->createFileWith('# this is some comment', 'folder1');
        $this->createAndUseConfigurationWith('folder1');
        $this->assertGitignoreContent(['# this is some comment', 'folder1']);
    }

    public function testVendorDir()
    {
        $configurator = $this->createConfigurationWith(/* noting */);
        $configurator->setAddLocalPackages(true);
        $configurator->configure($this->createContext());
        $this->assertGitignoreContent(['/vendor']);
    }
}
