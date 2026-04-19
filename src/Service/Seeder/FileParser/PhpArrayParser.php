<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Seeder\FileParser;

class PhpArrayParser implements ParserInterface
{
    public function supports(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php';
    }

    public function parse(string $path): array
    {
        $result = include $path;

        if (!is_array($result)) {
            throw new \RuntimeException(sprintf(
                'PHP seeder file "%s" must return an array, got %s',
                $path,
                get_debug_type($result)
            ));
        }

        return $result;
    }
}
