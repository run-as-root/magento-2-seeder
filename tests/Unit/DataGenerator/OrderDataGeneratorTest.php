<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\DataGenerator;

use DavidLambauer\Seeder\DataGenerator\OrderDataGenerator;
use DavidLambauer\Seeder\Service\GeneratedDataRegistry;
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
        $this->assertSame(['product', 'customer'], (new OrderDataGenerator())->getDependencies());
    }

    public function test_get_dependency_count_for_product(): void
    {
        $this->assertSame(50, (new OrderDataGenerator())->getDependencyCount('product', 1000));
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
}
