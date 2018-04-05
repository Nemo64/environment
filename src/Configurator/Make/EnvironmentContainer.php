<?php

namespace Nemo64\Environment\Configurator\Make;


trait EnvironmentContainer
{
    /**
     * @var string[]
     */
    private $environment = [];

    /**
     * @var string[]
     */
    private $requirements = [];

    /**
     * @param string $name
     * @param string $value
     */
    public function setEnvironment(string $name, ?string $value): void
    {
        if (isset($this->environment[$name])) {
            return;
        }

        if ($value === null) {
            $this->setEnvironmentRequired($name);
        } else {
            $this->environment[$name] = $value;
        }
    }

    /**
     * @return string[]
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    /**
     * @param string $name
     * @param string $message
     */
    public function setEnvironmentRequired(string $name, string $message = null): void
    {
        if (isset($this->requirements[$name])) {
            return;
        }

        $this->requirements[$name] = $message ?: "environment $name is not defined";
    }

    /**
     * @return string[]
     */
    public function getEnvironmentRequirement(): array
    {
        return $this->requirements;
    }
}