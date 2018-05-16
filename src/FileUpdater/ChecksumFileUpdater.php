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
        if (substr($firstLine, 0, strlen($this->comment)) === $this->comment) {
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
        $checksum = $this->getChecksum($handle);
        $result = $checksum !== null;
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
        if ($result === $oldContent && $checksum !== null) {
            return false;
        }

        $result = $this->getChecksumComment($content) . "\n" . $result;
        rewind($handle);
        fwrite($handle, $result);
        ftruncate($handle, strlen($result));
        fclose($handle);
        return true;
    }

    protected function handleConflict(string $content): bool
    {
        $answer = $this->io->select("The file {$this->filename} already exists but can't be managed. What should be done?", [
            'a' => "Append the managed content. You'll probably need to manually fix the file",
            'm' => "Try to merge the configuration. This works best when the checksum has been removed but might return garbage otherwise.",
            'i' => "Ignore, just add the checksum so this question doesn't come up again",
        ], false);

        switch ($answer) {
            case 'a':
                $currentContent = file_get_contents($this->filename);
                $checksumComment = $this->getChecksumComment($content);
                file_put_contents($this->filename, "$checksumComment\n$currentContent\n$content");
                return true;
            case 'm':
                return $this->doWrite($content);
            case 'i':
                $currentContent = file_get_contents($this->filename);
                $checksumComment = $this->getChecksumComment($content);
                return file_put_contents($this->filename, "$checksumComment\n$currentContent") !== false;
            default:
                return parent::handleConflict($content);
        }
    }

    /**
     * @param string $content
     * @return string
     */
    public function getChecksumComment(string $content): string
    {
        return $this->comment . LineMerger::createChecksum($content);
    }

}