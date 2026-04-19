<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CategoryDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CategoryDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_category(): void
    {
        $this->assertSame('category', (new CategoryDataGenerator())->getType());
    }

    public function test_get_order_returns_10(): void
    {
        $this->assertSame(10, (new CategoryDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_empty(): void
    {
        $this->assertSame([], (new CategoryDataGenerator())->getDependencies());
    }

    public function test_generate_returns_valid_category_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new CategoryDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('is_active', $data);
        $this->assertArrayHasKey('parent_id', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('url_key', $data);
        $this->assertTrue($data['is_active']);
        $this->assertNotEmpty($data['name']);
    }

    public function test_generate_can_nest_under_existing_categories(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 5, 'name' => 'Clothing']);

        $generator = new CategoryDataGenerator();
        $parentIds = [];
        for ($i = 0; $i < 30; $i++) {
            $data = $generator->generate($faker, $registry);
            $parentIds[] = $data['parent_id'];
        }

        $this->assertContains(5, $parentIds);
    }
}
