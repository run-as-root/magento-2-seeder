<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider;

/**
 * Source of locale-specific commerce wordlists consumed by CommerceProvider.
 * Each method returns a non-empty list of strings.
 */
interface CommerceLocaleInterface
{
    /** @return non-empty-list<string> */
    public function adjectives(): array;

    /** @return non-empty-list<string> */
    public function materials(): array;

    /** @return non-empty-list<string> */
    public function products(): array;

    /** @return non-empty-list<string> */
    public function departments(): array;
}
