<?php

namespace Nemo64\Environment\FileUpdater;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;

class WriteOnceFileUpdaterTest extends AbstractFileUpdaterTest
{
    function createInstance(string $filename, IOInterface $io = null): AbstractFileUpdater
    {
        return new WriteOnceFileUpdater($io ?? new NullIO(), $filename);
    }
}
