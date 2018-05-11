<?php


namespace Nemo64\Environment\FileUpdater;


interface FileUpdaterInterface
{
    public function write(string $content): void;

    public function read(): ?string;
}