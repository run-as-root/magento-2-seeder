<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\NewsletterSubscriberDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class NewsletterSubscriberDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_newsletter_subscriber(): void
    {
        $this->assertSame(
            'newsletter_subscriber',
            (new NewsletterSubscriberDataGenerator())->getType()
        );
    }

    public function test_get_order_returns_70(): void
    {
        $this->assertSame(70, (new NewsletterSubscriberDataGenerator())->getOrder());
    }

    public function test_get_dependencies_is_empty(): void
    {
        $this->assertSame([], (new NewsletterSubscriberDataGenerator())->getDependencies());
    }

    public function test_generate_without_customers_emits_guest_rows(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(1);
        $registry = new GeneratedDataRegistry();

        $data = (new NewsletterSubscriberDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('email', $data);
        $this->assertStringContainsString('@', $data['email']);
        $this->assertSame(0, $data['customer_id']);
        $this->assertSame(1, $data['store_id']);
        $this->assertSame(1, $data['subscriber_status']);
    }

    public function test_generate_with_customers_sometimes_links_to_customer(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['id' => 101, 'email' => 'seed-a@example.com']);
        $registry->add('customer', ['id' => 102, 'email' => 'seed-b@example.com']);
        $registry->add('customer', ['id' => 103, 'email' => 'seed-c@example.com']);

        $linked = 0;
        $guest = 0;
        for ($i = 0; $i < 200; $i++) {
            $faker->seed($i);
            $data = (new NewsletterSubscriberDataGenerator())->generate($faker, $registry);
            if ($data['customer_id'] > 0) {
                $linked++;
                $this->assertContains($data['email'], ['seed-a@example.com', 'seed-b@example.com', 'seed-c@example.com']);
                $this->assertContains($data['customer_id'], [101, 102, 103]);
            } else {
                $guest++;
                $this->assertSame(0, $data['customer_id']);
            }
        }

        $this->assertGreaterThan(40, $linked, 'Expected rough 50/50 split — linked too low');
        $this->assertGreaterThan(40, $guest, 'Expected rough 50/50 split — guest too low');
    }
}
