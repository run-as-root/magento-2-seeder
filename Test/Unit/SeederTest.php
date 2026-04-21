<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\SeedBuilder;
use RunAsRoot\Seeder\Seeder;
use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;
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
            new FakerFactory(new CommerceProviderFactory()),
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

    public function test_entry_methods_bind_to_their_expected_types(): void
    {
        $calls = [];
        $makeHandler = function (string $type) use (&$calls) {
            $handler = $this->createMock(\RunAsRoot\Seeder\Api\EntityHandlerInterface::class);
            $handler->method('create')->willReturnCallback(
                function () use (&$calls, $type): int {
                    $calls[] = $type;
                    return 1;
                }
            );
            return $handler;
        };

        $subject = new class(
            new EntityHandlerPool([
                'customer' => $makeHandler('customer'),
                'product'  => $makeHandler('product'),
                'order'    => $makeHandler('order'),
                'category' => $makeHandler('category'),
                'cms'      => $makeHandler('cms'),
                'wishlist' => $makeHandler('wishlist'),
            ]),
            new DataGeneratorPool([]),
            new FakerFactory(new CommerceProviderFactory()),
            new GeneratedDataRegistry(),
        ) extends Seeder {
            public function getType(): string { return 't'; }
            public function getOrder(): int { return 1; }
            public function run(): void {}

            public function hitAll(): void
            {
                $this->customers()->with(['_' => 1])->create();
                $this->products()->with(['_' => 1])->create();
                $this->orders()->with(['_' => 1])->create();
                $this->categories()->with(['_' => 1])->create();
                $this->cms()->with(['_' => 1])->create();
                $this->seed('wishlist')->with(['_' => 1])->create();
            }
        };

        $subject->hitAll();

        $this->assertSame(
            ['customer', 'product', 'order', 'category', 'cms', 'wishlist'],
            $calls
        );
    }
}
