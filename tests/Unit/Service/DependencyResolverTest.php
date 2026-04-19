<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\DependencyResolver;
use PHPUnit\Framework\TestCase;

final class DependencyResolverTest extends TestCase
{
    public function test_resolves_single_type_with_no_dependencies(): void
    {
        $customerGen = $this->createGeneratorMock('customer', 30, []);

        $pool = new DataGeneratorPool(['customer' => $customerGen]);
        $resolver = new DependencyResolver($pool);

        $result = $resolver->resolve(['customer' => 100]);

        $this->assertSame(['customer' => 100], $result);
    }

    public function test_resolves_transitive_dependencies(): void
    {
        $categoryGen = $this->createGeneratorMock('category', 10, []);
        $productGen = $this->createGeneratorMock('product', 20, ['category']);
        $productGen->method('getDependencyCount')
            ->with('category', 50)
            ->willReturn(10);

        $orderGen = $this->createGeneratorMock('order', 40, ['product', 'customer']);
        $orderGen->method('getDependencyCount')
            ->willReturnCallback(function (string $dep, int $count): int {
                return match ($dep) {
                    'product' => (int) ceil($count / 20),
                    'customer' => (int) ceil($count / 5),
                    default => 0,
                };
            });

        $customerGen = $this->createGeneratorMock('customer', 30, []);

        $pool = new DataGeneratorPool([
            'category' => $categoryGen,
            'product' => $productGen,
            'customer' => $customerGen,
            'order' => $orderGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['order' => 1000]);

        $this->assertSame(1000, $result['order']);
        $this->assertSame(50, $result['product']);
        $this->assertSame(200, $result['customer']);
        $this->assertSame(10, $result['category']);
    }

    public function test_user_overrides_win_over_calculated_counts(): void
    {
        $orderGen = $this->createGeneratorMock('order', 40, ['customer']);
        $orderGen->method('getDependencyCount')
            ->with('customer', 1000)
            ->willReturn(200);

        $customerGen = $this->createGeneratorMock('customer', 30, []);

        $pool = new DataGeneratorPool([
            'customer' => $customerGen,
            'order' => $orderGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['customer' => 500, 'order' => 1000]);

        $this->assertSame(500, $result['customer']);
    }

    public function test_result_is_sorted_by_generator_order(): void
    {
        $orderGen = $this->createGeneratorMock('order', 40, ['product']);
        $orderGen->method('getDependencyCount')->willReturn(10);
        $productGen = $this->createGeneratorMock('product', 20, []);

        $pool = new DataGeneratorPool([
            'order' => $orderGen,
            'product' => $productGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['order' => 100]);

        $keys = array_keys($result);
        $this->assertSame('product', $keys[0]);
        $this->assertSame('order', $keys[1]);
    }

    public function test_resolves_deps_for_dotted_product_subtype_by_aggregating_to_base_type(): void
    {
        $categoryGen = $this->createGeneratorMock('category', 10, []);
        $productGen = $this->createGeneratorMock('product', 20, ['category']);
        $productGen->method('getDependencyCount')
            ->with('category', 20)
            ->willReturn(4);

        $pool = new DataGeneratorPool([
            'category' => $categoryGen,
            'product' => $productGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['product.bundle' => 20]);

        $this->assertSame(4, $result['category']);
        $this->assertSame(20, $result['product.bundle']);
    }

    public function test_combines_plain_and_dotted_product_counts_for_dep_math(): void
    {
        $categoryGen = $this->createGeneratorMock('category', 10, []);
        $productGen = $this->createGeneratorMock('product', 20, ['category']);
        $productGen->method('getDependencyCount')
            ->with('category', 120)
            ->willReturn(24);

        $pool = new DataGeneratorPool([
            'category' => $categoryGen,
            'product' => $productGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['product' => 100, 'product.bundle' => 20]);

        $this->assertSame(24, $result['category']);
    }

    public function test_preserves_dotted_keys_in_output(): void
    {
        $categoryGen = $this->createGeneratorMock('category', 10, []);
        $productGen = $this->createGeneratorMock('product', 20, ['category']);
        $productGen->method('getDependencyCount')->willReturn(10);

        $pool = new DataGeneratorPool([
            'category' => $categoryGen,
            'product' => $productGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['product' => 100, 'product.bundle' => 20]);

        $this->assertArrayHasKey('product', $result);
        $this->assertArrayHasKey('product.bundle', $result);
        $this->assertSame(100, $result['product']);
        $this->assertSame(20, $result['product.bundle']);
    }

    private function createGeneratorMock(string $type, int $order, array $deps): DataGeneratorInterface
    {
        $mock = $this->createMock(DataGeneratorInterface::class);
        $mock->method('getType')->willReturn($type);
        $mock->method('getOrder')->willReturn($order);
        $mock->method('getDependencies')->willReturn($deps);

        return $mock;
    }
}
