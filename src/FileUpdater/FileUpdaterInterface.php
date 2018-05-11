<?php


namespace Nemo64\Environment\FileUpdater;


interface FileUpdaterInterface
{
    public function write(string $content): bool;

    public function read(): ?string;
}