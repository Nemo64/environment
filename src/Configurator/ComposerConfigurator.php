<?php

namespace Nemo64\Environment\Configurator;


use Composer\Config;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;
use Webmozart\PathUtil\Path;

class ComposerConfigurator implements ConfiguratorInterface
{
    public function getInfluences(): array
    {
        return [
            GitignoreConfigurator::class,
            MakefileConfigurator::class,
            PhpConfigurator::class,
        ];
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $this->configureMake($context, $container);
        $this->configureGitignore($context, $container);
    }

    protected function configureMake(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $make = $container->get(MakefileConfigurator::class);
        if ($make === null) {
            $context->info("makefile not available");
            return;
        }

        $vendorDir = $context->getComposer()->getConfig()->get('vendor-dir', Config::RELATIVE_PATHS);

        $make->setEnvironment('COMPOSER', 'docker-compose run --rm --no-deps php composer');
        $make['install']->addDependency($make[$vendorDir]);
        $make[$vendorDir]->addDependencyString('$(wildcard composer.*)');
        $make[$vendorDir]->addCommand("$(COMPOSER) install");

        $make['clean']->addCommand('rm -rf ' . escapeshellarg($vendorDir));

        $context->info("install and clean command installed");
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