<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\OrderDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class OrderDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_order(): void
    {
        $this->assertSame('order', (new OrderDataGenerator())->getType());
    }

    public function test_get_order_returns_40(): void
    {
        $this->assertSame(40, (new OrderDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_product_and_customer(): void
    {
        $this->assertSame(['product.simple', 'customer'], (new OrderDataGenerator())->getDependencies());
    }

    public function test_get_dependency_count_for_product(): void
    {
        $this->assertSame(50, (new OrderDataGenerator())->getDependencyCount('product.simple', 1000));
    }

    public function test_get_dependency_count_for_customer(): void
    {
        $this->assertSame(200, (new OrderDataGenerator())->getDependencyCount('customer', 1000));
    }

    public function test_generate_returns_valid_order_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', [
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'addresses' => [[
                'street' => ['123 Main St'],
                'city' => 'New York',
                'region_id' => 43,
                'postcode' => '10001',
                'country_id' => 'US',
                'telephone' => '555-0100',
            ]],
        ]);
        $registry->add('product', ['sku' => 'SEED-12345']);

        $data = (new OrderDataGenerator())->generate($faker, $registry);

        $this->assertSame('john@test.com', $data['customer_email']);
        $this->assertSame('John', $data['firstname']);
        $this->assertSame('Doe', $data['lastname']);
        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items']);
        $this->assertSame('SEED-12345', $data['items'][0]['sku']);
    }

    public function test_generate_includes_order_state_key(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', [
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'addresses' => [[
                'street' => ['123 Main St'],
                'city' => 'New York',
                'region_id' => 43,
                'postcode' => '10001',
                'country_id' => 'US',
                'telephone' => '555-0100',
            ]],
        ]);
        $registry->add('product', ['sku' => 'SEED-12345']);

        $data = (new OrderDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('order_state', $data);
        $this->assertContains(
            $data['order_state'],
            ['new', 'processing', 'complete', 'canceled', 'holded', 'closed']
        );
    }

    public function test_generate_respects_forced_state(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', [
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'addresses' => [[
                'street' => ['123 Main St'],
                'city' => 'New York',
                'region_id' => 43,
                'postcode' => '10001',
                'country_id' => 'US',
                'telephone' => '555-0100',
            ]],
        ]);
        $registry->add('product', ['sku' => 'SEED-12345']);

        $generator = new OrderDataGenerator();
        $generator->setForcedSubtype('canceled');

        for ($i = 0; $i < 10; $i++) {
            $data = $generator->generate($faker, $registry);
            $this->assertSame('canceled', $data['order_state']);
        }
    }

    public function test_generate_only_picks_simple_products_for_items(): void
    {
        $faker = \Faker\Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['email' => 'c@x.com', 'firstname' => 'C', 'lastname' => 'X', 'addresses' => [['street' => ['1 Main'], 'city' => 'NY', 'postcode' => '10001', 'country_id' => 'US', 'region_id' => 43, 'telephone' => '555-0100']]]);
        $registry->add('product', ['sku' => 'CFG-1', 'product_type' => 'configurable']);
        $registry->add('product', ['sku' => 'SIMPLE-1', 'product_type' => 'simple']);
        $registry->add('product', ['sku' => 'BND-1', 'product_type' => 'bundle']);

        $generator = new OrderDataGenerator();
        for ($i = 0; $i < 30; $i++) {
            $data = $generator->generate($faker, $registry);
            foreach ($data['items'] as $item) {
                $this->assertSame('SIMPLE-1', $item['sku'], 'Order items must only contain simple products');
            }
        }
    }

    public function test_generate_weighted_pick_yields_multiple_states_over_many_iterations(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', [
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'addresses' => [[
                'street' => ['123 Main St'],
                'city' => 'New York',
                'region_id' => 43,
                'postcode' => '10001',
                'country_id' => 'US',
                'telephone' => '555-0100',
            ]],
        ]);
        $registry->add('product', ['sku' => 'SEED-12345']);

        $generator = new OrderDataGenerator();
        $observed = [];
        for ($i = 0; $i < 300; $i++) {
            $data = $generator->generate($faker, $registry);
            $observed[$data['order_state']] = true;
        }

        $this->assertGreaterThanOrEqual(3, count($observed));
    }
}
