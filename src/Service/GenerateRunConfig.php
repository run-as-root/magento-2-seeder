<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

final class GenerateRunConfig
{
    /** @param array<string, int> $counts */
    public function __construct(
        public readonly array $counts,
        public readonly string $locale = 'en_US',
        public readonly ?int $seed = null,
        public readonly bool $fresh = false,
        public readonly bool $stopOnError = false,
    ) {
    }
}
