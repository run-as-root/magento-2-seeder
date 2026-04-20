<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\WishlistDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class WishlistDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_wishlist(): void
    {
        $this->assertSame('wishlist', (new WishlistDataGenerator())->getType());
    }

    public function test_get_order_returns_60(): void
    {
        $this->assertSame(60, (new WishlistDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_customer_and_product(): void
    {
        $this->assertSame(
            ['customer', 'product'],
            (new WishlistDataGenerator())->getDependencies()
        );
    }

    public function test_get_dependency_count_customer_is_one_to_one(): void
    {
        $gen = new WishlistDataGenerator();
        $this->assertSame(10, $gen->getDependencyCount('customer', 10));
        $this->assertSame(0, $gen->getDependencyCount('product', 10));
    }

    public function test_generate_without_customer_throws(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('product', ['id' => 1, 'sku' => 'seed-1']);

        $this->expectException(\RuntimeException::class);
        (new WishlistDataGenerator())->generate($faker, $registry);
    }

    public function test_generate_without_product_throws(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['id' => 42, 'email' => 'a@example.com']);

        $this->expectException(\RuntimeException::class);
        (new WishlistDataGenerator())->generate($faker, $registry);
    }

    public function test_generate_shape_with_registered_entities(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(3);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['id' => 42, 'email' => 'a@example.com']);
        for ($i = 1; $i <= 10; $i++) {
            $registry->add('product', ['id' => $i, 'sku' => "seed-{$i}"]);
        }

        $data = (new WishlistDataGenerator())->generate($faker, $registry);

        $this->assertSame(42, $data['customer_id']);
        $this->assertSame(0, $data['shared']);
        $this->assertIsArray($data['items']);
        $this->assertGreaterThanOrEqual(1, count($data['items']));
        $this->assertLessThanOrEqual(5, count($data['items']));
        foreach ($data['items'] as $item) {
            $this->assertArrayHasKey('product_id', $item);
            $this->assertSame(1, $item['qty']);
            $this->assertContains($item['product_id'], range(1, 10));
        }
    }
}
