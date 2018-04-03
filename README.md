[![Build Status](https://travis-ci.org/Nemo64/environment.svg?branch=master)](https://travis-ci.org/Nemo64/environment)
[![Latest Stable Version](https://poser.pugx.org/nemo64/environment/v/stable)](https://packagist.org/packages/nemo64/environment)
[![Total Downloads](https://poser.pugx.org/nemo64/environment/downloads)](https://packagist.org/packages/nemo64/environment)
[![License](https://poser.pugx.org/nemo64/environment/license)](https://packagist.org/packages/nemo64/environment)

# a basic php development environment

This composer plugin aims to be an assist in building your base environment in potentially multiple projects.

It does that by providing apis to define common config files. It is also highly extendable so you can add your own configurations for what your needs are.

## how it works

Other than most scaffold projects, this is a composer plugin and aims to not only create your base files but also to update them.

This plugin will run on every `composer install` and `composer update`, checks if some conditions have changed and potentially updates some files.
For example:
- you get a basic docker php environment that is keeped up to date with your composer configured `platform.php` version.
- you get a `makefile` to easily start/stop and install your project (just run `make serve`) to install and start everything. Depending on your configuration you can also add other dependency management commands to the install command.
- your .gitignore file will always contain all installed libraries even if they are installed outside the vendor dir which is common for older projects like typo3.

These is just the basic functionality. You can implement your own rules by creating a class extending the `ConfiguratorInterface`. In this class you'll be able to either create your own files or configure the already existing Configurators eg. to add more gitignore rules.

## extend functionality

Configurators aren't limited to other composer-plugins. You can add them to your root project or even other libraries if they need to add something to a project outside their folder. If you need to add a file to the projects `.gitignore` file, you can simply add your own Configurator like this:

```PHP
<?php

class MyConfigurator implements \Nemo64\Environment\Configurator\ConfiguratorInterface
{
    public function getInfluences(): array
    {
        return [
            \Nemo64\Environment\Configurator\GitignoreConfigurator::class,
        ];
    }
    
    public function configure(\Nemo64\Environment\ExecutionContext $context): void
    {
         $context->get(\Nemo64\Environment\Configurator\GitignoreConfigurator::class)->add('/tmpdir');
    }
}
``` 

And hint to the file in your `composer.json`:

```JSON
{
    "extra": {
        "nemo64/environment": {
            "classes": ["Namespace\\MyConfigurator"]
        }
    }
}
```

Done. Your `configure` method will be called every time you use composer. You can check if other extensions get installed, read config files. Even ask the user questions using io (but be sure those are only asked once).

### guideline

If you want your configurator to be useful, make sure it is highly adaptive. The `GitignoreConfigurator` won't remove rules you have made and even preserve comments. Therefor an outside user does not have to lead way of configuring a file that is right there. He just adds a rule and it works as expected.