<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\ProductDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class ProductDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_product(): void
    {
        $this->assertSame('product', (new ProductDataGenerator())->getType());
    }

    public function test_get_order_returns_20(): void
    {
        $this->assertSame(20, (new ProductDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_category(): void
    {
        $this->assertSame(['category'], (new ProductDataGenerator())->getDependencies());
    }

    public function test_get_dependency_count_for_category(): void
    {
        $generator = new ProductDataGenerator();
        $this->assertSame(10, $generator->getDependencyCount('category', 50));
        $this->assertSame(20, $generator->getDependencyCount('category', 100));
    }

    public function test_generate_returns_valid_product_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new ProductDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('sku', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('short_description', $data);
        $this->assertArrayHasKey('weight', $data);
        $this->assertArrayHasKey('url_key', $data);
        $this->assertArrayHasKey('qty', $data);
        $this->assertArrayHasKey('image_url', $data);
        $this->assertStringStartsWith('SEED-', $data['sku']);
        $this->assertGreaterThan(0, $data['price']);
    }

    public function test_generate_assigns_category_from_registry(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 5]);
        $registry->add('category', ['id' => 8]);

        $data = (new ProductDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('category_ids', $data);
        $this->assertNotEmpty($data['category_ids']);
    }

    public function test_generate_produces_unique_skus(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();

        $skus = [];
        for ($i = 0; $i < 50; $i++) {
            $data = $generator->generate($faker, $registry);
            $skus[] = $data['sku'];
        }

        $this->assertSame(50, count(array_unique($skus)));
    }
}
