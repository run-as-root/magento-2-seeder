<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Faker\Provider;

use RunAsRoot\Seeder\Faker\Provider\CommerceLocaleInterface;
use RunAsRoot\Seeder\Faker\Provider\CommerceProvider;
use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

final class CommerceProviderTest extends TestCase
{
    public function test_product_adjective_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->productAdjective(), $locale->adjectives());
    }

    public function test_product_material_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->productMaterial(), $locale->materials());
    }

    public function test_product_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->product(), $locale->products());
    }

    public function test_product_department_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->productDepartment(), $locale->departments());
    }

    public function test_product_name_returns_three_word_string_from_component_lists(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $name = $faker->productName();

        $words = explode(' ', $name);
        $this->assertCount(3, $words, "expected 3-word name, got '$name'");
        $this->assertContains($words[0], $locale->adjectives());
        $this->assertContains($words[1], $locale->materials());
        $this->assertContains($words[2], $locale->products());
    }

    public function test_seeded_generator_produces_deterministic_product_name(): void
    {
        $locale = new EnUs();

        $faker1 = $this->fakerWithCommerce($locale);
        $faker1->seed(1234);
        $name1 = $faker1->productName();

        $faker2 = $this->fakerWithCommerce($locale);
        $faker2->seed(1234);
        $name2 = $faker2->productName();

        $this->assertSame($name1, $name2);
    }

    private function fakerWithCommerce(CommerceLocaleInterface $locale): Generator
    {
        $faker = Factory::create('en_US');
        $faker->addProvider(new CommerceProvider($faker, $locale));
        return $faker;
    }
}
