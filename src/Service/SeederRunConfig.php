<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

final class SeederRunConfig
{
    /**
     * @param string[] $only
     * @param string[] $exclude
     */
    public function __construct(
        public readonly array $only = [],
        public readonly array $exclude = [],
        public readonly bool $fresh = false,
        public readonly bool $stopOnError = false,
    ) {
    }
}
