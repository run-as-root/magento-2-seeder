<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

interface SeederInterface
{
    public function getType(): string;

    public function getOrder(): int;

    public function run(): void;
}
