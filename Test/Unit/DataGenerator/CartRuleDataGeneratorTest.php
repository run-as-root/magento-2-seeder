<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CartRuleDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CartRuleDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_cart_rule(): void
    {
        $this->assertSame('cart_rule', (new CartRuleDataGenerator())->getType());
    }

    public function test_get_order_returns_50(): void
    {
        $this->assertSame(50, (new CartRuleDataGenerator())->getOrder());
    }

    public function test_generate_shape_contains_required_keys(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(1);
        $registry = new GeneratedDataRegistry();

        $data = (new CartRuleDataGenerator())->generate($faker, $registry);

        foreach (['name', 'is_active', 'website_ids', 'customer_group_ids', 'simple_action', 'coupon'] as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: {$key}");
        }
        $this->assertSame(1, $data['is_active']);
        $this->assertContains($data['simple_action'], ['by_percent', 'by_fixed', 'free_shipping']);
        $this->assertArrayHasKey('code', $data['coupon']);
        $this->assertMatchesRegularExpression('/^[A-Z]+\d{1,3}-[A-Z0-9]{6}$/', $data['coupon']['code']);
    }

    public function test_generate_action_distribution_roughly_60_30_10(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $gen = new CartRuleDataGenerator();

        $counts = ['by_percent' => 0, 'by_fixed' => 0, 'free_shipping' => 0];
        for ($i = 0; $i < 1000; $i++) {
            $faker->seed($i);
            $counts[$gen->generate($faker, $registry)['simple_action']]++;
        }

        $this->assertGreaterThan(500, $counts['by_percent'], 'by_percent weight ~60');
        $this->assertGreaterThan(200, $counts['by_fixed'], 'by_fixed weight ~30');
        $this->assertGreaterThan(50, $counts['free_shipping'], 'free_shipping weight ~10');
    }

    public function test_generate_free_shipping_has_zero_discount_amount(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $gen = new CartRuleDataGenerator();

        for ($i = 0; $i < 300; $i++) {
            $faker->seed($i);
            $data = $gen->generate($faker, $registry);
            if ($data['simple_action'] === 'free_shipping') {
                $this->assertSame(0.0, (float) $data['discount_amount']);
                return;
            }
        }
        $this->fail('No free_shipping action produced in 300 iterations');
    }

    public function test_generate_percent_amount_within_5_to_30(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $gen = new CartRuleDataGenerator();

        for ($i = 0; $i < 300; $i++) {
            $faker->seed($i);
            $data = $gen->generate($faker, $registry);
            if ($data['simple_action'] === 'by_percent') {
                $this->assertGreaterThanOrEqual(5, $data['discount_amount']);
                $this->assertLessThanOrEqual(30, $data['discount_amount']);
            }
        }
    }
}
