<?php

namespace Nemo64\Environment\Configurator;


use Composer\IO\IOInterface;
use Nemo64\Environment\ExecutionContext;
use Webmozart\PathUtil\Path;

class GitignoreConfigurator implements ConfiguratorInterface
{
    private $rules = [];

    public function getInfluences(): array
    {
        return [];
    }

    public function add(string $rule): void
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

    public function configure(ExecutionContext $context): void
    {
        // all packages are normally in the vendor dir and this won't do anything
        // however, if an installer puts it somewhere else, than this rule will prevent it from being versioned
        foreach ($context->getLocalRepository()->getCanonicalPackages() as $package) {
            // the add function will test if the rule is already covered
            $this->add($context->getInstallationManager()->getInstallPath($package));
        }

        $updates = $this->update($context->getPath('.gitignore'));
        $context->getIo()->write("<info>$updates</info> new gitignore rules", true, IOInterface::VERBOSE);
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