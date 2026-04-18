<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

interface EntityHandlerInterface
{
    public function create(array $data): void;

    public function clean(): void;

    public function getType(): string;
}
