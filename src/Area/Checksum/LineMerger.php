<?php

namespace Nemo64\Environment\Area\Checksum;


class LineMerger
{
    const HASH_LENGTH = 4;

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private static function createLineChecksum(string $line): string
    {
        return substr(base64_encode(sha1($line, true)), 0, static::HASH_LENGTH);
    }

    public static function createChecksum(string $content): string
    {
        return implode('', array_map('self::createLineChecksum', explode("\n", $content)));
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

    private static function arraySearchBackwards(array $haystack, $needle, int $offset, int $low = 0): int
    {
        for ($i = $offset; $i >= $low; --$i) {
            if ($haystack[$i] === $needle) {
                return $i;
            }
        }

        return -1;
    }

    public static function mergeContent(?string $oldChecksum, string $curContent, string $newContent): string
    {
        $oldChecksum = empty($oldChecksum) ? [] : str_split($oldChecksum, static::HASH_LENGTH);
        $curContent = empty($curContent) ? [] : explode("\n", $curContent);
        $newContent = empty($newContent) ? [] : explode("\n", $newContent);
        $curChecksum = array_map('static::createLineChecksum', $curContent);
        $newChecksum = array_map('static::createLineChecksum', $newContent);

        $old = 0;
        $cur = 0;
        $new = 0;
        $result = [];
        while ($old < count($oldChecksum) || $cur < count($curContent) || $new < count($newContent)) {

            $noMoreOldContent = count($oldChecksum) <= $old;
            if ($noMoreOldContent) {
                if (count($curContent) > $cur) {
                    array_push($result, ...array_slice($curContent, $cur));
                }

                if (count($newContent) > $new && (count($oldChecksum) > 0 || count($curContent) === 0)) {
                    array_push($result, ...array_slice($newContent, $new));
                }

                break;
            }

            // handle new content being moved backwards
            $movedIndex = self::arraySearch($oldChecksum, $newChecksum[$new], $old);
            if ($movedIndex >= 0) {
                $movement = $movedIndex - $old;
                $old += $movement;
                $cur += $movement;
            }

            // handle new content being moved forward
            $movedIndex = self::arraySearch($newChecksum, $oldChecksum[$old], $new);
            if ($movedIndex > $new) {
                array_push($result, ...array_slice($newContent, $new, $movedIndex - $new));
                $new = $movedIndex;
                continue;
            }

            // handle lines being added/replaced by the user
            $nextMatchingLine = self::arraySearch($curChecksum, $oldChecksum[$old], $cur);
            if ($nextMatchingLine >= 0) {
                $offset = $nextMatchingLine - $cur;
                if ($offset > 0) {
                    array_push($result, ...array_slice($curContent, $cur, $offset));
                }

                if (isset($newContent[$new])) {
                    $result[] = $newContent[$new];
                }
                $old = $old + 1;
                $cur = $nextMatchingLine + 1;
                $new = $new + 1;
                continue;
            }

            // i must assume that this line has been removed
            $old++;
            $new++;
        }

        return implode("\n", $result);
    }
}