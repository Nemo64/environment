<?php

namespace Nemo64\Environment\Configurator;


use Composer\IO\IOInterface;
use Nemo64\Environment\ConfiguratorContainer;
use Nemo64\Environment\ExecutionContext;
use Webmozart\PathUtil\Path;

class GitignoreConfigurator implements ConfiguratorInterface
{
    /**
     * @var string[]
     */
    private $rules = [];

    public function getInfluences(): array
    {
        return [
            MakefileConfigurator::class
        ];
    }

    /**
     * This adds a line to the gitignore file.
     * The line will be interpreted as a filename to prevent duplicates.
     * This should mostly work even for optimizing negative expressions like !.gitkeep
     *
     * @param string $rule
     * @see GitignoreConfigurator::add
     */
    public function addLine(string $rule): void
    {
        foreach ($this->rules as $index => $existingRule) {
            // if this exclude path is already covered by another rule ignore it
            if (Path::isBasePath($existingRule, $rule)) {
                return;
            }

            // if another rule is more precise than the current one remove it
            if (Path::isBasePath($rule, $existingRule)) {
                unset($this->rules[$index]);
            }
        }

        $this->rules[] = $rule;
    }

    /**
     * Adds a file to the gitignore.
     * The difference to #addLine is that a leading slash will be prepended.
     * This is useful if you have a path that you want to exclude.
     * You should prefer this to #addLine
     *
     * @param string $relativePath
     * @see GitignoreConfigurator::addLine
     */
    public function add(string $relativePath): void
    {
        $path = '/' . ltrim($relativePath, '/');
        $this->addLine($path);
    }

    public function configure(ExecutionContext $context, ConfiguratorContainer $container): void
    {
        $updates = $this->update($context->getPath('.gitignore'));
        $verbosity = $updates > 0 ? IOInterface::NORMAL : IOInterface::VERBOSE;
        $msg = $updates === 1 ? "<info>1</info> new gitignore rule" : "<info>$updates</info> new gitignore rules";
        $context->getIo()->write($msg, true, $verbosity);
    }

    protected function update(string $path): int
    {
        $fileExists = file_exists($path);
        $hasTailingEmptyLine = false;
        if ($fileExists) {
            $handle = fopen($path, 'r');
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                $hasTailingEmptyLine = false;
                switch (substr($line, 0, 1)) {
                    case '!': // if you explicitly want something, you can get it
                    case '#': // if you commented a rule out, then i won't add it again ~ ain't I being nice?
                        $this->remove(substr($line, 1));
                        break;
                    case '':
                        $hasTailingEmptyLine = true;
                        break;
                    default:
                        $this->remove($line);
                }
            }
        }

        $newRules = count($this->rules);
        if ($newRules > 0) {
            $handle = fopen($path, 'a');
            if ($fileExists && !$hasTailingEmptyLine) {
                fputs($handle, PHP_EOL);
            }
            foreach ($this->rules as $rule) {
                fputs($handle, $rule . PHP_EOL);
            }
            fclose($handle);
        }

        return $newRules;
    }

    private function remove(string $rule): void
    {
        foreach ($this->rules as $index => $existingRule) {
            // if another rule is more (or equally as) precise than the current one remove it
            if (Path::isBasePath($rule, $existingRule)) {
                unset($this->rules[$index]);
            }
        }
    }
}