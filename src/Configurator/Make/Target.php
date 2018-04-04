<?php

namespace Nemo64\Environment\Configurator\Make;


class Target
{
    use EnvironmentContainer;

    const POSITION_INIT = 20;
    const POSITION_TOP = 10;
    const POSITION_NORMAL = 0;
    const POSITION_END = -10;
    const POSITION_CLEANUP = -20;

    /**
     * @var string
     */
    private $name;

    /**
     * @var Target[]
     */
    private $dependencies = [];

    /**
     * @var string[]
     */
    private $commands = [];

    /**
     * Target constructor.
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $name = $this->getName();
        $result = [];

        foreach ($this->getEnvironment() as $key => $value) {
            $result[] = "$name: $key=$value";
        }

        if ($this->getDependencies()) {
            $names = array_map(function (Target $target) {
                return $target->getName();
            }, $this->getDependencies());
            $result[] = $name . ': ' . implode(' ', $names);
        } else {
            $result[] = $name . ':';
        }

        foreach ($this->getEnvironmentRequirement() as $variable => $errorMessage) {
            $result[] = "ifndef $variable\n\t$(error " . escapeshellarg($errorMessage) . ")\nendif";
        }

        $detailedCommands = $this->getDetailedCommands();

        $conditionGroups = [];
        foreach ($detailedCommands as $detailedCommand) {
            $index = $detailedCommand['priority'] . $detailedCommand['condition'];
            $conditionGroups[$index][] = $detailedCommand;
        }

        foreach ($conditionGroups as $conditionGroup) {
            $condition = reset($conditionGroup)['condition'];
            if (!$condition) {
                foreach ($conditionGroup as $detailedCommand) {
                    $result[] = "\t" . str_replace("\n", "\n\t", $detailedCommand['command']);
                }
            } else {
                $result[] = $condition;
                foreach ($conditionGroup as $detailedCommand) {
                    if (!$detailedCommand['command']) {
                        continue;
                    }

                    $result[] = "\t" . str_replace("\n", "\n\t", $detailedCommand['command']);
                }
                if (count(array_filter(array_column($conditionGroup, 'else')))) {
                    $result[] = "else";
                    foreach ($conditionGroup as $detailedCommand) {
                        if (!$detailedCommand['else']) {
                            continue;
                        }

                        $result[] = "\t" . str_replace("\n", "\n\t", $detailedCommand['else']);
                    }
                }
                $result[] = "endif";
            }
        }

        return implode(PHP_EOL, $result);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param Target $dependency
     */
    public function addDependency(Target $dependency): void
    {
        $this->dependencies[$dependency->getName()] = $dependency;
    }

    /**
     * @return Target[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @param string $command
     * @param int $priority
     */
    public function addCommand(string $command, int $priority = 0): void
    {
        $this->addConditionalCommand('', $command, '', $priority);
    }

    /**
     * @param string $condition
     * @param string $then
     * @param string $else
     * @param int $priority
     */
    public function addConditionalCommand(string $condition, string $then, string $else = '', int $priority = 0): void
    {
        $this->commands[] = [
            'priority' => $priority,
            'condition' => $condition,
            'command' => $then,
            'else' => $else,
            'index' => count($this->commands)
        ];
    }

    /**
     * @param string $environment
     * @param string $command
     * @param string $else
     * @param int $priority
     */
    public function addCommandForEnvironment(string $environment, string $command, string $else = '', int $priority = 0): void
    {
        $this->addConditionalCommand("ifeq ($(ENVIRONMENT), $environment)", $command, $else, $priority);
    }

    /**
     * @return string[]
     */
    public function getCommands(): array
    {
        return array_column($this->getDetailedCommands(), 'command');
    }

    /**
     * @return array
     */
    public function getDetailedCommands(): array
    {
        // sort commands in a stable way (keep the order the same if the priority is the same)
        // the higher the priority, the further up the command so this is a descending sort
        usort($this->commands, function (array $a, array $b): int {
            return $b['priority'] <=> $a['priority']
                ?: $a['index'] <=> $b['index'];
        });

        return $this->commands;
    }
}