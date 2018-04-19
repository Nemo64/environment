<?php

namespace Nemo64\Environment\Area\Checksum;


class LineMerger
{
    const HASH_LENGTH = 4;

    private static function createLineChecksum(string $line): string
    {
        return substr(base64_encode(sha1($line, true)), 0, static::HASH_LENGTH);
    }

    public static function createChecksum(string $content): string
    {
        return implode('', array_map('static::createLineChecksum', explode("\n", $content)));
    }

    private static function arraySearch(array $haystack, $needle, int $offset): int
    {
        for ($i = $offset; $i < count($haystack); ++$i) {
            if ($haystack[$i] === $needle) {
                return $i;
            }
        }

        return -1;
    }

    public static function mergeContent(string $oldChecksum, string $curContent, string $newContent): string
    {
        $oldChecksum = str_split($oldChecksum, static::HASH_LENGTH);
        $curContent = explode("\n", $curContent);
        $newContent = explode("\n", $newContent);
        $curChecksum = array_map('static::createLineChecksum', $curContent);

        $old = 0;
        $cur = 0;
        $new = 0;
        $result = [];
        while ($old < count($oldChecksum) || $cur < count($curContent) || $new < count($newContent)) {

            $noMoreOldContent = count($oldChecksum) <= $old;
            if ($noMoreOldContent) {
                $leftOverContent = array_slice($curContent, $cur);
                if (count($leftOverContent) > 0) {
                    array_push($result, ...$leftOverContent);
                }
                break;
            }

            $nextMatchingLine = self::arraySearch($curChecksum, $oldChecksum[$old], $cur);
            if ($nextMatchingLine >= 0) {
                $offset = $nextMatchingLine - $cur;
                if ($offset > 0) {
                    array_push($result, ...array_slice($curContent, $cur, $offset));
                }

                $result[] = $newContent[$new];
                $old = $old + 1;
                $cur = $nextMatchingLine + 1;
                $new = $nextMatchingLine + 1;
                continue;
            }

            // i must assume that this line has been removed
            $old++;
            $new++;
        }

        return implode("\n", $result);
    }
}