<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Faker\Factory;
use Faker\Generator;

class FakerFactory
{
    public function create(string $locale = 'en_US', ?int $seed = null): Generator
    {
        $faker = Factory::create($locale);

        if ($seed !== null) {
            $faker->seed($seed);
        }

        return $faker;
    }
}
