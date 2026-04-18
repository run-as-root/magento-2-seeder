<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use PHPUnit\Framework\TestCase;

final class DataGeneratorPoolTest extends TestCase
{
    public function test_get_returns_generator_for_registered_type(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $pool = new DataGeneratorPool(['customer' => $generator]);

        $this->assertSame($generator, $pool->get('customer'));
    }

    public function test_get_throws_for_unregistered_type(): void
    {
        $pool = new DataGeneratorPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No data generator registered for type: unknown');

        $pool->get('unknown');
    }

    public function test_has_returns_true_for_registered_type(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $pool = new DataGeneratorPool(['customer' => $generator]);

        $this->assertTrue($pool->has('customer'));
    }

    public function test_has_returns_false_for_unregistered_type(): void
    {
        $pool = new DataGeneratorPool([]);

        $this->assertFalse($pool->has('order'));
    }

    public function test_get_all_returns_all_generators(): void
    {
        $a = $this->createMock(DataGeneratorInterface::class);
        $b = $this->createMock(DataGeneratorInterface::class);

        $pool = new DataGeneratorPool(['customer' => $a, 'product' => $b]);

        $this->assertCount(2, $pool->getAll());
    }
}
