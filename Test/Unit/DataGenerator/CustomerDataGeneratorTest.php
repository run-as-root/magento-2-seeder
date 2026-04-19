<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CustomerDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CustomerDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_customer(): void
    {
        $this->assertSame('customer', (new CustomerDataGenerator())->getType());
    }

    public function test_get_order_returns_30(): void
    {
        $this->assertSame(30, (new CustomerDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_empty(): void
    {
        $this->assertSame([], (new CustomerDataGenerator())->getDependencies());
    }

    public function test_generate_returns_valid_customer_data_with_addresses(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new CustomerDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('firstname', $data);
        $this->assertArrayHasKey('lastname', $data);
        $this->assertArrayHasKey('password', $data);
        $this->assertArrayHasKey('addresses', $data);
        $this->assertNotEmpty($data['addresses']);
        $this->assertStringContainsString('@', $data['email']);

        $address = $data['addresses'][0];
        $this->assertArrayHasKey('street', $address);
        $this->assertArrayHasKey('city', $address);
        $this->assertArrayHasKey('postcode', $address);
        $this->assertArrayHasKey('country_id', $address);
        $this->assertArrayHasKey('telephone', $address);
    }

    public function test_generate_telephone_matches_magento_regex(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new CustomerDataGenerator();

        for ($i = 0; $i < 200; $i++) {
            $faker->seed($i);
            $data = $generator->generate($faker, $registry);
            $telephone = $data['addresses'][0]['telephone'];
            $this->assertMatchesRegularExpression('/^[0-9\-\(\) \+]+$/', $telephone, "Seed {$i} produced invalid phone: {$telephone}");
            $this->assertNotEmpty(trim($telephone));
        }
    }

    public function test_generate_produces_unique_emails(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new CustomerDataGenerator();

        $emails = [];
        for ($i = 0; $i < 50; $i++) {
            $data = $generator->generate($faker, $registry);
            $emails[] = $data['email'];
        }

        $this->assertSame(50, count(array_unique($emails)));
    }
}
