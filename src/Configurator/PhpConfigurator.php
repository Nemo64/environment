<?php

namespace Nemo64\Environment\Configurator;


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
        $docker = $container->get(DockerConfigurator::class);
        $docker->createDockerfile('Dockerfile-php', [
            'FROM chialab/php:7.1-apache',
            'RUN ' . implode(" \\\n && ", [
                "sed -i 's#/var/www/html#/var/www/public#g' /etc/apache2/sites-enabled/000-default.conf",
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
                '127.0.0.1:80:80'
            ]
        ]);
    }
}