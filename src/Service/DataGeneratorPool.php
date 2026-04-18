<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;

class DataGeneratorPool
{
    /** @param array<string, DataGeneratorInterface> $generators */
    public function __construct(
        private readonly array $generators = [],
    ) {
    }

    public function get(string $type): DataGeneratorInterface
    {
        if (!isset($this->generators[$type])) {
            throw new \InvalidArgumentException("No data generator registered for type: {$type}");
        }

        return $this->generators[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->generators[$type]);
    }

    /** @return array<string, DataGeneratorInterface> */
    public function getAll(): array
    {
        return $this->generators;
    }
}
