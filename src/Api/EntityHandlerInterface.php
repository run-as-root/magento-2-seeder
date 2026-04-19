<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

interface EntityHandlerInterface
{
    /**
     * Persist an entity described by $data and return its primary key.
     *
     * The id is captured by GenerateRunner and written back into the registry so that
     * later generators (e.g. ProductDataGenerator needing real category ids) can
     * reference it.
     */
    public function create(array $data): int;

    public function clean(): void;

    public function getType(): string;
}
