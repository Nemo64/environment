<?php

namespace Nemo64\Environment\Area;


interface AreaInterface
{
    public function exists($handle): bool;

    public function write($handle, string $newContent): void;

    public function read($handle): string;
}