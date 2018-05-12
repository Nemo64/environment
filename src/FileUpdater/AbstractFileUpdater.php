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

    public function getFilename(): string
    {
        return $this->filename;
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

    final public function write(string $content): bool
    {
        if (!$this->canMerge()) {
            return $this->handleConflict($content);
        }

        $dirname = dirname($this->filename);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
            $this->io->write("Create folder <info>$dirname</info>");
        }

        if (!$this->doWrite($content)) {
            return false;
        }

        $this->io->write("Updated file <info>{$this->filename}</info>");
        return true;
    }

    protected function handleConflict(string $content): bool
    {
        $this->io->writeError("File <info>{$this->filename}</info> can't be merged. ~ignored", true, IOInterface::VERBOSE);
        $this->io->write("The content of the file would have been:\n$content\n", true, IOInterface::VERY_VERBOSE);
        return true;
    }
}