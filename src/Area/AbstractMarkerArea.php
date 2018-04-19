<?php

namespace Nemo64\Environment\Area;


abstract class AbstractMarkerArea implements AreaInterface
{
    protected abstract function isStart(string $line): bool;

    protected abstract function isEnd(string $line): bool;

    protected abstract function wrapContent(string $oldWrapped, string $newContent): string;

    private function seekToStart($handle, bool $seekInside): int
    {
        rewind($handle);

        while (($line = fgets($handle)) !== false) {
            if ($this->isStart($line)) {
                if (!$seekInside) {
                    fseek($handle, -strlen($line), SEEK_CUR);
                }

                break;
            }
        }

        return ftell($handle);
    }

    private function readToEnd($handle): array
    {
        $result = '';

        while (($line = fgets($handle)) !== false) {
            if ($this->isEnd($line)) {
                return [$result, true];
            }

            $result .= $line;
        }

        return [$result, false];
    }

    public function exists($handle): bool
    {
        $this->seekToStart($handle, false);
        return feof($handle) === false;
    }

    public function write($handle, string $content): void
    {
        $startAreaPosition = $this->seekToStart($handle, false);
        $noStartMarker = feof($handle);
        list($oldContent, $hasEndMarker) = $this->readToEnd($handle);
        $endAreaPosition = $startAreaPosition + strlen($oldContent) + ($hasEndMarker ? -1 : 0);

        $newContent = $this->wrapContent($oldContent, $content) . "\n";
        if ($noStartMarker) {
            $newContent = "\n" . $newContent;
        }

        $sizeDifference = strlen($content) - strlen($oldContent);

        $tailingContent = stream_get_contents($handle, -1, $endAreaPosition);
        fseek($handle, $endAreaPosition + $sizeDifference);
        fwrite($handle, $tailingContent);

        fseek($handle, $startAreaPosition);
        fwrite($handle, $newContent);
        ftruncate($handle, $startAreaPosition + strlen($newContent) + strlen($tailingContent));
    }

    public function read($handle): string
    {
        $this->seekToStart($handle, true);
        list($content, $hasEndMarker) = $this->readToEnd($handle);
        return $hasEndMarker ? substr($content, 0, -1) : $content;
    }
}