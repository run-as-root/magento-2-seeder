<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Service;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;

class EntityHandlerPool
{
    /** @param array<string, EntityHandlerInterface> $handlers */
    public function __construct(
        private readonly array $handlers = [],
    ) {
    }

    public function get(string $type): EntityHandlerInterface
    {
        if (!isset($this->handlers[$type])) {
            throw new \InvalidArgumentException("No entity handler registered for type: {$type}");
        }

        return $this->handlers[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /** @return array<string, EntityHandlerInterface> */
    public function getAll(): array
    {
        return $this->handlers;
    }
}
