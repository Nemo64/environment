<?php

namespace Nemo64\Environment\Configurator;


use Composer\Config;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;
use Nemo64\Environment\FileUpdater\WriteOnceFileUpdater;
use Webmozart\PathUtil\Path;

class ComposerConfigurator implements ConfiguratorInterface
{
    public function getInfluences(): array
    {
        return [
            GitignoreConfigurator::class,
            MakefileConfigurator::class,
            DockerConfigurator::class,
        ];
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $this->configureShortcutScript($context, $container);
        $this->configureMake($context, $container);
        $this->configureGitignore($context, $container);
    }

    protected function configureShortcutScript(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $shortcut = new WriteOnceFileUpdater($context->getIo(), $context->getPath('composer'));
        $command = 'docker-compose run --rm --no-deps --user www-data php composer "$@"';
        $shortcut->write("#!/usr/bin/env sh\n\n$command");
        chmod($shortcut->getFilename(), 0755);
    }

    protected function configureMake(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $make = $container->get(MakefileConfigurator::class);
        if ($make === null) {
            $context->info("makefile not available");
            return;
        }

        $vendorDir = $context->getComposer()->getConfig()->get('vendor-dir', Config::RELATIVE_PATHS);

        // i can't name the composer variable "COMPOSER" since this environment variable is used by composer itself
        $make->setEnvironment('COMPOSER_CMD', './composer');
        $make['install']->addDependency($make[$vendorDir]);
        $make[$vendorDir]->addDependencyString('$(wildcard composer.*)');
        $make[$vendorDir]->addCommand("$(COMPOSER_CMD) install");

        $make['clean']->addCommand('rm -rf ' . escapeshellarg($vendorDir));

        $context->info("install and clean command installed");

        $docker = $container->get(DockerConfigurator::class);
        $docker->defineService('php', [
            'volumes' => [
                '~/.composer:/root/.composer:cached'
            ]
        ]);
    }

    protected function configureGitignore(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $gitignore = $container->get(GitignoreConfigurator::class);
        if ($gitignore === null) {
            $context->info("gitignore not available");
            return;
        }

        $vendorDir = $context->getComposer()->getConfig()->get('vendor-dir');
        $gitignore->add(Path::makeRelative($vendorDir, $context->getRootDir()));

        // all packages are normally in the vendor dir and this won't do anything
        // however, if an installer puts it somewhere else, than this rule will prevent it from being versioned
        foreach ($context->getLocalRepository()->getCanonicalPackages() as $package) {
            // the add function will test if the rule is already covered
            $packagePath = $context->getInstallationManager()->getInstallPath($package);

            if (Path::isBasePath($packagePath, $context->getRootDir())) {
                $context->info(sprintf(
                    'The package "<info>%s</info>" is installed in "<info>%s</info>". Ignored.',
                    $package->getName(),
                    $packagePath
                ));
                continue;
            }

            $gitignore->add(Path::makeRelative($packagePath, $context->getRootDir()));
        }

        $context->info("gitignore prepared");
    }
}