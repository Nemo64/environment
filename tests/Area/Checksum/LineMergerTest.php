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
                ""
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
        $checksum = implode('', array_map('Nemo64\Environment\Area\Checksum\LineMerger::createChecksum', explode("\n", $oldContent)));
        $this->assertEquals($expectResult, LineMerger::mergeContent($checksum, $userContent, $newContent));
    }
}
