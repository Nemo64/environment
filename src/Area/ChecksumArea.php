<?php

namespace Nemo64\Environment\Area;



use Nemo64\Environment\Area\Checksum\LineMerger;

class ChecksumArea extends AbstractMarkerArea
{
    const HASH_LENGTH = 4;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $startComment;

    /**
     * @var string
     */
    private $endComment;

    public function __construct(string $name, string $startComment = '# ', string $endComment = '')
    {
        $this->name = $name;
        $this->startComment = $startComment;
        $this->endComment = $endComment;
    }

    protected function getStartCommentPrefix(): string
    {
        return $this->startComment . $this->name . ': ';
    }

    protected function isStart(string $line): bool
    {
        $startComment = $this->getStartCommentPrefix();
        return substr($line, 0, strlen($startComment)) === $startComment;
    }

    protected function isEnd(string $line): bool
    {
        return "\n" === $line;
    }

    protected function wrapContent(string $oldWrap, string $newContent): string
    {
        $newChecksum = LineMerger::createChecksum($newContent);
        $startCommentPrefix = $this->getStartCommentPrefix();
        if (substr($oldWrap, 0, strlen($startCommentPrefix)) !== $startCommentPrefix) {
            return "{$startCommentPrefix}{$newChecksum}\n{$newContent}\n";
        }

        $prefixLength = strlen($startCommentPrefix);
        $oldChecksum = substr($oldWrap, $prefixLength, strpos($oldWrap, "\n", $prefixLength) - $prefixLength);
        $userContent = substr($oldWrap, strpos($oldWrap, "\n", $prefixLength) + 1, -1);

        $resultChecksum = LineMerger::createChecksum($newContent);
        $result = LineMerger::mergeContent($oldChecksum, $userContent, $newContent);

        return "{$startCommentPrefix}{$resultChecksum}\n{$result}\n";
    }
}