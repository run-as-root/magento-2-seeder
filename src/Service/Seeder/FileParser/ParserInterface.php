<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Seeder\FileParser;

interface ParserInterface
{
    public function supports(string $path): bool;

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException When the file cannot be parsed into an associative array.
     */
    public function parse(string $path): array;
}
