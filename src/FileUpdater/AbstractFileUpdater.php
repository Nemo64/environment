<?php

namespace Nemo64\Environment\FileUpdater;


use Composer\IO\IOInterface;

abstract class AbstractFileUpdater implements FileUpdaterInterface
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var IOInterface
     */
    protected $io;

    public function __construct(IOInterface $io, string $filename)
    {
        $this->io = $io;
        $this->filename = $filename;
    }

    abstract public function canMerge(): bool;

    abstract protected function doRead(): string;

    final public function read(): ?string
    {
        if (!file_exists($this->filename)) {
            return null;
        }

        return $this->doRead();
    }

    abstract protected function doWrite(string $content): bool;

    final public function write(string $content): void
    {
        if (!$this->canMerge()) {
            $this->handleConflict($content);
            return;
        }

        if (!$this->doWrite($content)) {
            return;
        }

        $this->io->write("Updated file <info>{$this->filename}</info>");
    }

    protected function handleConflict(string $content): void
    {
        $this->io->writeError("File <info>{$this->filename}</info> can't be merged. ~ignored", true, IOInterface::VERBOSE);
        $this->io->write("The content of the file would have been:\n$content\n", true, IOInterface::VERY_VERBOSE);
    }
}