<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\SeedBuilder;
use RunAsRoot\Seeder\Seeder;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeederTest extends TestCase
{
    private function makeSubject(): Seeder
    {
        return new class(
            new EntityHandlerPool([]),
            new DataGeneratorPool([]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        ) extends Seeder {
            public function getType(): string { return 't'; }
            public function getOrder(): int { return 1; }
            public function run(): void {}

            public function publicCustomers(): SeedBuilder  { return $this->customers(); }
            public function publicProducts(): SeedBuilder   { return $this->products(); }
            public function publicOrders(): SeedBuilder     { return $this->orders(); }
            public function publicCategories(): SeedBuilder { return $this->categories(); }
            public function publicCms(): SeedBuilder        { return $this->cms(); }
            public function publicSeed(string $t): SeedBuilder { return $this->seed($t); }
        };
    }

    public function test_customers_returns_builder_bound_to_customer_type(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicCustomers());
    }

    public function test_products_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicProducts());
    }

    public function test_orders_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicOrders());
    }

    public function test_categories_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicCategories());
    }

    public function test_cms_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicCms());
    }

    public function test_seed_with_custom_type_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicSeed('wishlist'));
    }

    public function test_each_call_returns_a_fresh_builder(): void
    {
        $subject = $this->makeSubject();
        $this->assertNotSame($subject->publicCustomers(), $subject->publicCustomers());
    }
}
