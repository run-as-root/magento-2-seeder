<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Seeder\FileParser;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlParser implements ParserInterface
{
    public function supports(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'yaml' || $extension === 'yml';
    }

    public function parse(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read YAML seeder file "%s"', $path));
        }

        try {
            $decoded = Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new \RuntimeException(
                sprintf('Invalid YAML in seeder file "%s": %s', $path, $e->getMessage()),
                0,
                $e
            );
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException(sprintf(
                'YAML seeder file "%s" must contain a mapping at the root',
                $path
            ));
        }

        return $decoded;
    }
}
