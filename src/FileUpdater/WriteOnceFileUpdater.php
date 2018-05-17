<?php

namespace Nemo64\Environment\FileUpdater;


use Composer\IO\IOInterface;

class WriteOnceFileUpdater extends AbstractFileUpdater
{
    public function canMerge(): bool
    {
        return !file_exists($this->filename);
    }

    protected function doRead(): string
    {
        return file_get_contents($this->filename);
    }

    protected function doWrite(string $content): bool
    {
        file_put_contents($this->filename, $content);
        return true;
    }

    protected function handleConflict(string $content): bool
    {
        $this->io->write("File <info>{$this->name}</info> already exists.", true, IOInterface::VERBOSE);
        return false;
    }
}