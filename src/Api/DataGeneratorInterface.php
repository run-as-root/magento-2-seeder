<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

interface DataGeneratorInterface
{
    public function getType(): string;

    public function getOrder(): int;

    /** Generate ONE entity's data array, compatible with EntityHandler::create() */
    public function generate(Generator $faker, GeneratedDataRegistry $registry): array;

    /** @return string[] Entity types this generator depends on */
    public function getDependencies(): array;

    /** How many of $dependencyType are needed for $selfCount of this type */
    public function getDependencyCount(string $dependencyType, int $selfCount): int;
}
