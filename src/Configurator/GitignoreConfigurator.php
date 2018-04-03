<?php

namespace Nemo64\Environment\Configurator;


use Composer\IO\IOInterface;
use Nemo64\Environment\ExecutionContext;
use Webmozart\PathUtil\Path;

class GitignoreConfigurator implements ConfiguratorInterface
{
    /**
     * @var string[]
     */
    private $rules = [];

    /**
     * @var bool
     */
    private $addLocalPackages = true;

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

    public function addAbsolute(string $rule): void
    {
        $this->add('/' . ltrim($rule, '/'));
    }

    public function configure(ExecutionContext $context): void
    {
        if ($this->isAddLocalPackages()) {
            $this->addLocalPackages($context);
        }

        $updates = $this->update($context->getPath('.gitignore'));
        $verbosity = $updates > 0 ? IOInterface::NORMAL : IOInterface::VERBOSE;
        $msg = $updates === 1 ? "<info>1</info> new gitignore rule" : "<info>$updates</info> new gitignore rules";
        $context->getIo()->write($msg, true, $verbosity);
    }

    protected function addLocalPackages(ExecutionContext $context): void
    {
        $vendorDir = $context->getComposer()->getConfig()->get('vendor-dir');
        $this->addAbsolute(Path::makeRelative($vendorDir, $context->getRootDir()));

        // all packages are normally in the vendor dir and this won't do anything
        // however, if an installer puts it somewhere else, than this rule will prevent it from being versioned
        foreach ($context->getLocalRepository()->getCanonicalPackages() as $package) {
            // the add function will test if the rule is already covered
            $packagePath = $context->getInstallationManager()->getInstallPath($package);
            $this->addAbsolute(Path::makeRelative($packagePath, $context->getRootDir()));
        }
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

    public function isAddLocalPackages(): bool
    {
        return $this->addLocalPackages;
    }

    public function setAddLocalPackages(bool $addLocalPackages): void
    {
        $this->addLocalPackages = $addLocalPackages;
    }

}