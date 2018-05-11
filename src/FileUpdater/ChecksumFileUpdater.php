<?php

namespace Nemo64\Environment\FileUpdater;


use Composer\IO\IOInterface;
use Nemo64\Environment\FileUpdater\Checksum\LineMerger;

class ChecksumFileUpdater extends AbstractFileUpdater
{
    protected $comment;

    public function __construct(IOInterface $io, string $filename, string $commentPrefix = '# ')
    {
        parent::__construct($io, $filename);
        $this->comment = $commentPrefix . 'checksum: ';
    }

    private function getChecksum($handle): ?string
    {
        rewind($handle);

        $firstLine = fgets($handle);
        if (substr($firstLine, 0, strlen($this->comment))) {
            return substr($firstLine, strlen($this->comment), -1);
        }

        return null;
    }

    public function canMerge(): bool
    {
        if (!file_exists($this->filename)) {
            return true;
        }

        $handle = fopen($this->filename, 'r');
        $result = $this->getChecksum($handle) !== null;
        fclose($handle);
        return $result;
    }

    protected function doRead(): string
    {
        $handle = fopen($this->filename, 'r');
        $checksum = $this->getChecksum($handle);
        if (!$checksum) {
            rewind($handle);
        }

        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    protected function doWrite(string $content): bool
    {
        $handle = fopen($this->filename, 'c+');
        $checksum = $this->getChecksum($handle);
        if (empty($checksum)) {
            rewind($handle);
        }

        $oldContent = stream_get_contents($handle);
        $result = LineMerger::mergeContent($checksum, $oldContent, $content);
        if ($result === $oldContent) {
            return false;
        }

        $result = $this->comment . LineMerger::createChecksum($content) . "\n" . $result;
        rewind($handle);
        fwrite($handle, $result);
        ftruncate($handle, strlen($result));
        fclose($handle);
        return true;
    }
}