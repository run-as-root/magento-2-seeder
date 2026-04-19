<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Seeder\FileParser;

class JsonParser implements ParserInterface
{
    public function supports(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json';
    }

    public function parse(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read JSON seeder file "%s"', $path));
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                sprintf('Invalid JSON in seeder file "%s": %s', $path, $e->getMessage()),
                0,
                $e
            );
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException(sprintf(
                'JSON seeder file "%s" must contain an object at the root',
                $path
            ));
        }

        return $decoded;
    }
}
