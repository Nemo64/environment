<?php

namespace Nemo64\Environment\Area;



use Nemo64\Environment\Area\Checksum\LineMerger;

class ChecksumArea implements AreaInterface
{
    private $comment;

    public function __construct(string $commentPrefix = '# ')
    {
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

    public function exists($handle): bool
    {
        return $this->getChecksum($handle) !== null;
    }

    public function write($handle, string $newContent): void
    {
        $checksum = $this->getChecksum($handle);
        $oldContent = stream_get_contents($handle);
        $result = LineMerger::mergeContent($checksum, $oldContent, $newContent);

        $result = $this->comment . LineMerger::createChecksum($newContent) . "\n" . $result;
        rewind($handle);
        fwrite($handle, $result);
        ftruncate($handle, strlen($result));
    }

    public function read($handle): string
    {
        $checksum = $this->getChecksum($handle);
        return stream_get_contents($handle);
    }
}