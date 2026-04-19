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

    public function test_generate_includes_product_type_key(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();

        $data = $generator->generate($faker, $registry);

        $this->assertArrayHasKey('product_type', $data);
        $this->assertContains(
            $data['product_type'],
            ['simple', 'configurable', 'bundle', 'grouped', 'downloadable'],
        );
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

    public function test_generate_respects_forced_subtype(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();
        $generator->setForcedSubtype('bundle');

        for ($i = 0; $i < 20; $i++) {
            $data = $generator->generate($faker, $registry);
            $this->assertSame('bundle', $data['product_type']);
        }
    }

    public function test_generate_returns_weighted_random_when_not_forced(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(1234);
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();

        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $data = $generator->generate($faker, $registry);
            $seen[$data['product_type']] = true;
        }

        $this->assertGreaterThanOrEqual(
            3,
            count($seen),
            'Weighted random across 200 iterations should produce at least 3 distinct subtypes',
        );
    }

    public function test_generate_emits_downloadable_payload_when_subtype_is_downloadable(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();
        $generator->setForcedSubtype('downloadable');

        $data = $generator->generate($faker, $registry);

        $this->assertArrayHasKey('downloadable', $data);
        $this->assertArrayHasKey('links', $data['downloadable']);
        $this->assertNotEmpty($data['downloadable']['links']);
        $firstLink = $data['downloadable']['links'][0];
        $this->assertArrayHasKey('title', $firstLink);
        $this->assertArrayHasKey('sample_text', $firstLink);
    }

    public function test_generate_assigns_product_to_least_used_category(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 10]);
        $registry->add('category', ['id' => 11]);
        $registry->add('category', ['id' => 12]);
        $registry->add('product', ['category_ids' => [10]]);
        $registry->add('product', ['category_ids' => [11]]);

        $data = (new ProductDataGenerator())->generate($faker, $registry);

        $this->assertSame([12], $data['category_ids']);
    }

    public function test_generate_fills_all_categories_before_doubling_up(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 10]);
        $registry->add('category', ['id' => 11]);
        $registry->add('category', ['id' => 12]);

        $generator = new ProductDataGenerator();
        $assigned = [];
        for ($i = 0; $i < 3; $i++) {
            $data = $generator->generate($faker, $registry);
            $assigned[] = $data['category_ids'];
            $registry->add('product', $data);
        }

        $this->assertSame([[10], [11], [12]], $assigned);
    }

    public function test_generate_includes_reviews_array(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();

        $data = (new ProductDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('reviews', $data);
        $this->assertIsArray($data['reviews']);
    }

    public function test_generate_reviews_contain_required_keys(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();

        // Generate many products to make it likely at least some reviews are produced.
        $sawAtLeastOneReview = false;
        for ($i = 0; $i < 30; $i++) {
            $data = $generator->generate($faker, $registry);
            foreach ($data['reviews'] as $review) {
                $sawAtLeastOneReview = true;
                $this->assertArrayHasKey('nickname', $review);
                $this->assertArrayHasKey('title', $review);
                $this->assertArrayHasKey('detail', $review);
                $this->assertArrayHasKey('rating', $review);
                $this->assertGreaterThanOrEqual(1, $review['rating']);
                $this->assertLessThanOrEqual(5, $review['rating']);
            }
        }

        $this->assertTrue(
            $sawAtLeastOneReview,
            'Expected to see at least one review across 30 generated products',
        );
    }

    public function test_generate_distributes_evenly_across_categories(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 10]);
        $registry->add('category', ['id' => 11]);
        $registry->add('category', ['id' => 12]);
        $registry->add('category', ['id' => 13]);

        $generator = new ProductDataGenerator();
        $counts = [10 => 0, 11 => 0, 12 => 0, 13 => 0];
        for ($i = 0; $i < 8; $i++) {
            $data = $generator->generate($faker, $registry);
            $counts[$data['category_ids'][0]]++;
            $registry->add('product', $data);
        }

        $this->assertSame([10 => 2, 11 => 2, 12 => 2, 13 => 2], $counts);
    }
}
