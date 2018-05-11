<?php

namespace Nemo64\Environment\Area\Checksum;

use PHPUnit\Framework\TestCase;

class LineMergerTest extends TestCase
{
    public static function mergeSets()
    {
        return [
            'simple' => [
                "hallo",
                "hallo",
                "welt",
                "welt"
            ],
            'addLine' => [
                "text",
                "text",
                "text\ntext",
                "text\ntext"
            ],
            'insertedAfter' => [
                "hallo",
                "hallo\nnewline",
                "welt",
                "welt\nnewline"
            ],
            'insertedBefore' => [
                "hallo",
                "newline\nhallo",
                "welt",
                "newline\nwelt"
            ],
            'insertedInside' => [
                "hallo\nwelt",
                "hallo\nnewline\nwelt",
                "hallo\ntester",
                "hallo\nnewline\ntester"
            ],
            'removedBefore' => [
                "hallo\nwelt",
                "welt",
                "hallo\ntester",
                "tester"
            ],
            'removedAfter' => [
                "hallo\nwelt",
                "hallo",
                "tester\nwelt",
                "tester"
            ],
            'removedInside' => [
                "hallo\nschöne\nwelt",
                "hallo\nwelt",
                "tschüss\nalter\nfreund",
                "tschüss\nfreund"
            ],
            'empty' => [
                "text",
                "",
                "text\ntext",
                "text"
            ],
            'shorten' => [
                "line1\nline2\nline3",
                "line1\nline2\nline3",
                "line1",
                "line1"
            ],
            'completelyDifferent' => [
                null,
                "diff1\ndiff2\ndiff3",
                "line1\nline2\nline3",
                "diff1\ndiff2\ndiff3",
            ],
            'moveAddedLineForward' => [
                "line1\nline2\nline3",
                "line1\nline1.5\nline2\nline3",
                "line0\nline1\nline2\nline3",
                "line0\nline1\nline1.5\nline2\nline3",
            ],
            'moveAddedLineBackward' => [
                "line1\nline2\nline3",
                "line1\nline2\nline2.5\nline3",
                "line2\nline3",
                "line2\nline2.5\nline3",
            ],
            'moveEditedLine' => [
                "line1\nline2\nline3",
                "line1\nline2.5\nline3",
                "line0\nline1\nline2\nline3",
                "line0\nline1\nline2.5\nline3",
            ],
            'moveEditedLineBackward' => [
                "line1\nline2\nline3",
                "line1\nline2.5\nline3",
                "line2\nline3",
                "line2.5\nline3",
            ],
            'removeEditedLine' => [
                "line1\nline2\nline3",
                "line1\nline2.5\nline3",
                "line1\nline3",
                "line1\nline3",
            ]
        ];
    }

    /**
     * @dataProvider mergeSets
     * @param string $oldContent
     * @param string $userContent
     * @param string $newContent
     * @param string $expectResult
     */
    public function testMerge($oldContent, $userContent, $newContent, $expectResult)
    {
        $checksum = $oldContent !== null
            ? implode('', array_map('Nemo64\Environment\Area\Checksum\LineMerger::createChecksum', explode("\n", $oldContent)))
            : null;
        $this->assertEquals($expectResult, LineMerger::mergeContent($checksum, $userContent, $newContent));
    }
}
