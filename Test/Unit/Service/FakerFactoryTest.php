<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;
use RunAsRoot\Seeder\Service\FakerFactory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

final class FakerFactoryTest extends TestCase
{
    public function test_creates_faker_with_default_locale(): void
    {
        $factory = new FakerFactory(new CommerceProviderFactory());
        $faker = $factory->create();

        $this->assertInstanceOf(Generator::class, $faker);
    }

    public function test_creates_faker_with_custom_locale(): void
    {
        $factory = new FakerFactory(new CommerceProviderFactory());
        $faker = $factory->create('de_DE');

        $this->assertInstanceOf(Generator::class, $faker);
    }

    public function test_creates_deterministic_faker_with_seed(): void
    {
        $factory = new FakerFactory(new CommerceProviderFactory());
        $faker1 = $factory->create('en_US', 42);
        $name1 = $faker1->name();

        $faker2 = $factory->create('en_US', 42);
        $name2 = $faker2->name();

        $this->assertSame($name1, $name2);
    }

    public function test_creates_random_faker_without_seed(): void
    {
        $factory = new FakerFactory(new CommerceProviderFactory());
        $faker = $factory->create('en_US', null);

        $this->assertNotEmpty($faker->name());
    }

    public function test_created_faker_exposes_commerce_methods(): void
    {
        $factory = new FakerFactory(new CommerceProviderFactory());
        $faker = $factory->create('en_US');

        $name = $faker->productName();
        $this->assertNotEmpty($name);
        $this->assertCount(3, explode(' ', $name));

        $department = $faker->productDepartment();
        $this->assertNotEmpty($department);
    }

    public function test_no_arg_constructor_still_provides_commerce_methods(): void
    {
        // BC guarantee: callers who instantiated FakerFactory without arguments
        // (pre-1.4.0) keep working — the factory self-constructs its default
        // CommerceProviderFactory.
        $factory = new FakerFactory();
        $faker = $factory->create('en_US');

        $this->assertInstanceOf(Generator::class, $faker);
        $this->assertNotEmpty($faker->productName());
    }
}
