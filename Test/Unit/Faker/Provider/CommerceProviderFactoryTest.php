<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Faker\Provider;

use RunAsRoot\Seeder\Faker\Provider\CommerceProvider;
use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;
use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CommerceProviderFactoryTest extends TestCase
{
    public function test_create_with_en_us_returns_provider_backed_by_english_wordlists(): void
    {
        $factory = new CommerceProviderFactory();
        $faker = Factory::create('en_US');

        $provider = $factory->create('en_US', $faker);

        $this->assertInstanceOf(CommerceProvider::class, $provider);
        $faker->addProvider($provider);
        $this->assertContains($faker->productAdjective(), (new EnUs())->adjectives());
    }

    public function test_create_with_unknown_locale_falls_back_to_en_us_wordlists_silently(): void
    {
        $factory = new CommerceProviderFactory();
        $faker = Factory::create('xx_YY');

        $provider = $factory->create('xx_YY', $faker);

        $this->assertInstanceOf(CommerceProvider::class, $provider);
        $faker->addProvider($provider);
        // Silent fallback: no exception, no warning, English wordlists used.
        $this->assertContains($faker->productDepartment(), (new EnUs())->departments());
    }

    public function test_create_with_de_de_currently_falls_back_to_en_us(): void
    {
        // v1 scope: only en_US is mapped. Fallback is explicit and documented.
        $factory = new CommerceProviderFactory();
        $faker = Factory::create('de_DE');

        $provider = $factory->create('de_DE', $faker);

        $faker->addProvider($provider);
        $this->assertContains($faker->productAdjective(), (new EnUs())->adjectives());
    }
}
