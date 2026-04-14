<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\Service;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;
use DavidLambauer\Seeder\Service\EntityHandlerPool;
use PHPUnit\Framework\TestCase;

final class EntityHandlerPoolTest extends TestCase
{
    public function test_get_returns_handler_for_registered_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $this->assertSame($handler, $pool->get('customer'));
    }

    public function test_get_throws_for_unregistered_type(): void
    {
        $pool = new EntityHandlerPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No entity handler registered for type: unknown');

        $pool->get('unknown');
    }

    public function test_has_returns_true_for_registered_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $this->assertTrue($pool->has('customer'));
    }

    public function test_has_returns_false_for_unregistered_type(): void
    {
        $pool = new EntityHandlerPool([]);

        $this->assertFalse($pool->has('order'));
    }

    public function test_get_all_returns_all_handlers(): void
    {
        $customerHandler = $this->createMock(EntityHandlerInterface::class);
        $productHandler = $this->createMock(EntityHandlerInterface::class);

        $pool = new EntityHandlerPool([
            'customer' => $customerHandler,
            'product' => $productHandler,
        ]);

        $this->assertCount(2, $pool->getAll());
        $this->assertSame($customerHandler, $pool->getAll()['customer']);
    }
}
