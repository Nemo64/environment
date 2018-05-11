<?php

namespace Nemo64\Environment\Configurator;


use Nemo64\Environment\Area\ChecksumArea;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;
use Symfony\Component\Yaml\Yaml;

class DockerConfigurator implements ConfiguratorInterface
{
    /**
     * To make editing a file a better experience, i create those keys below every time in the same order.
     * If the user adds for example a link and you add a link too, then they'll get inserted in the same place.
     * If I wouldn't  do that, then your "links" attribute might be somewhere else then the user defined "links".
     * That would result in the file having 2 "links" attributes which is invalid in yaml and even if it wasn't: it wouldn't work.
     *
     * There is some special treatment for the "image" and "build" keys. As soon as one is defined, the other one is removed.
     * Defining them here results in them being on top which is nicer to look at.
     */
    const DEFAULT_SERVICE_FIELDS = [
        'image' => null,
        'build' => null,
        'links' => [],
        'ports' => [],
        'volumes' => [],
        'tmpfs' => [],
        'environment' => [],
    ];

    /**
     * To make the file even nicer to look at (and in some cases even work) i add some characteristics using regex here.
     */
    const REPLACEMENTS = [

        // add spaces between first and second level items
        '/^((  )?\w+:[^\n]*)$/m' => "\n\\1",

        // remove quotes around strings with a colon in them
        // don't remove them around numbers since it might have a purpose that they are quoted (like the compose version)
        // the first character must not be a { or [ or else yaml might interpret it as an object/array
        '/\'(?![\d\.]+\')(?:[\[\{])((:?[^\n#>]*)+)\'/' => "\\1",

        // swap empty objects with empty arrays
        '/(    (links|ports|volumes|tmpfs|environment)):\s*\{\s*\}/' => "\\1: []",
    ];

    private $services = [];
    private $volumes = [];
    private $dockerfiles = [];

    /**
     * @var ChecksumArea
     */
    private $area;

    public function __construct()
    {
        $this->area = new ChecksumArea();
    }

    public function getInfluences(): array
    {
        return [
            MakefileConfigurator::class,
            GitignoreConfigurator::class
        ];
    }

    public function defineService(string $name, array $definition): void
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = self::DEFAULT_SERVICE_FIELDS;
        }

        foreach ($definition as $key => $value) {
            if (isset($this->services[$name][$key]) && is_array($this->services[$name][$key])) {
                $this->services[$name][$key] = array_merge($this->services[$name][$key], $value);
            } else {
                $this->services[$name][$key] = $value;
            }
        }

        if (isset($this->services[$name]['build'])) {
            unset($this->services[$name]['image']);
        }

        if (isset($this->services[$name]['image'])) {
            unset($this->services[$name]['build']);
        }
    }

    public function defineVolume(string $name, array $definition = []): void
    {
        $this->volumes[$name] = array_replace_recursive($this->services[$name] ?? [], $definition);
    }

    public function createDockerfile(string $name, array $lines): void
    {
        if (isset($this->dockerfiles[$name])) {
            throw new \LogicException("Dockerfile $name is already created");
        }

        $this->dockerfiles[$name] = $lines;
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        // Create the dockerignore file.
        // In our environment we want nothing to be transferred. currently no update strategy
        $dockerIgnoreFilename = $context->getPath('.dockerignore');
        if (!file_exists($dockerIgnoreFilename)) {
            file_put_contents($dockerIgnoreFilename, '*');
        }

        foreach ($this->dockerfiles as $name => $dockerfile) {
            $this->area->write(
                fopen($context->getPath($name), 'c+'),
                implode("\n", $dockerfile)
            );
        }

        $dockerComposeContent = [
            'version' => '3',
            'services' => $this->services,
            'volumes' => $this->volumes
        ];
        $yaml = Yaml::dump($dockerComposeContent, 4, 2);
        $yaml = preg_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $yaml);

        $this->area->write(
            fopen($context->getPath('docker-compose.yml'), 'c+'),
            $yaml
        );

        $make = $container->get(MakefileConfigurator::class);
        if ($make !== null) {
            $make['install']->addDependency($make['docker-compose.log']);
            $make['docker-compose.log']->addCommand('docker-compose build |tee docker-compose.log');
            foreach ($this->dockerfiles as $name => $content) {
                $make['docker-compose.log']->addDependencyString($name);
            }

            $gitignore = $container->get(GitignoreConfigurator::class);
            if ($gitignore) {
                $gitignore->add('docker-compose.log');
            }

            $make['.PHONY']->addDependency($make['start']);
            $make['start']->setDescription("Start all services.");
            $make['start']->addDependency($make['install']);
            $make['start']->addCommand('docker-compose up --detach');

            $make['.PHONY']->addDependency($make['stop']);
            $make['stop']->setDescription("Stop all services.");
            $make['stop']->addCommand('docker-compose down --remove-orphans');

            $make['.PHONY']->addDependency($make['clean']);
            $make['clean']->addDependency($make['stop']);
            $make['clean']->addCommand('docker-compose down -v');
            $make['clean']->addCommand('rm -f docker-compose.log');

            $make['.PHONY']->addDependency($make['log']);
            $make['log']->setDescription("Show (and follow) the log files.");
            $make['log']->setEnvironment('FOLLOW', 1);
            $make['log']->setEnvironment('LINES', 20);
            $make['log']->addCommand('docker-compose logs --tail=$(LINES) $(if $(filter 1,$(FOLLOW)),--follow)');
        }
    }
}