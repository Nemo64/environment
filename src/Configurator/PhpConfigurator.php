<?php

namespace Nemo64\Environment\Configurator;


use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use Composer\Package\Version\VersionParser;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;

class PhpConfigurator implements ConfiguratorInterface
{
    public function getInfluences(): array
    {
        return [
            DockerConfigurator::class
        ];
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $versionParser = new VersionParser();
        $platform = $context->getComposer()->getConfig()->get('platform');
        if (!isset($platform['php'])) {
            $phpVersion = $versionParser->normalize(PHP_VERSION);
            $question = "What version of php do you want to use? (default $phpVersion): ";
            $platform['php'] = $context->getIo()->ask($question, $phpVersion);
            $context->getComposer()->getConfig()->merge(['platform' => $platform]);

            $composerFilePath = trim(getenv('COMPOSER')) ?: $context->getPath('composer.json');
            $composerFile = new JsonFile($composerFilePath, null, $context->getIo());
            $configSource = new JsonConfigSource($composerFile);
            $configSource->addConfigSetting('platform', $platform);
        }

        $version = implode('.', array_slice(explode('.', $versionParser->normalize($platform['php'])), 0, 2));

        $docker = $container->get(DockerConfigurator::class);
        $docker->createDockerfile('Dockerfile-php', [
            "FROM chialab/php:$version-apache",
            'RUN ' . implode(" \\\n && ", [
                "sed -i 's#/var/www/html#/var/www\${DOCUMENT_ROOT}#g' /etc/apache2/sites-enabled/000-default.conf",
                "a2enmod alias deflate expires headers rewrite",
                "echo 'opcache.enable_file_override=On' >> /usr/local/etc/php/conf.d/php.ini",
                "echo 'opcache.revalidate_freq=0' >> /usr/local/etc/php/conf.d/php.ini",
            ])
        ]);

        $docker->defineService('php', [
            'build' => [
                'context' => '.',
                'dockerfile' => 'Dockerfile-php'
            ],
            'volumes' => [
                '.:/var/www:delegated'
            ],
            'working_dir' => '/var/www',
            'user' => 'root:www-data',
            'ports' => [
                '80:80'
            ],
            'environment' => [
                'DOCUMENT_ROOT=/' . $container->getOption('document-root')
            ]
        ]);
    }
}