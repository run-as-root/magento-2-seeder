<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;
use Faker\Factory;
use Faker\Generator;

class FakerFactory
{
    private readonly CommerceProviderFactory $commerceProviderFactory;

    public function __construct(?CommerceProviderFactory $commerceProviderFactory = null)
    {
        $this->commerceProviderFactory = $commerceProviderFactory ?? new CommerceProviderFactory();
    }

    public function create(string $locale = 'en_US', ?int $seed = null): Generator
    {
        $faker = Factory::create($locale);
        $faker->addProvider($this->commerceProviderFactory->create($locale, $faker));

        if ($seed !== null) {
            $faker->seed($seed);
        }

        return $faker;
    }
}
