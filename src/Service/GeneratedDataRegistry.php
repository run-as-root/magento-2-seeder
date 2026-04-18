<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

class GeneratedDataRegistry
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $data = [];

    public function add(string $type, array $entityData): void
    {
        $this->data[$type][] = $entityData;
    }

    /** @return list<array<string, mixed>> */
    public function getAll(string $type): array
    {
        return $this->data[$type] ?? [];
    }

    /** @return array<string, mixed> */
    public function getRandom(string $type): array
    {
        $items = $this->getAll($type);

        if ($items === []) {
            throw new \RuntimeException("No generated data for type: {$type}");
        }

        return $items[array_rand($items)];
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
