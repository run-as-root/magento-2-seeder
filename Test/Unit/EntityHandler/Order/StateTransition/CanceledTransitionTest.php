<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\CanceledTransition;

final class CanceledTransitionTest extends TestCase
{
    public function test_get_state_returns_canceled(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $transition = new CanceledTransition($repository);

        $this->assertSame('canceled', $transition->getState());
    }

    public function test_apply_calls_cancel_then_saves(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);

        $order->expects($this->once())->method('cancel')->willReturnSelf();
        $repository->expects($this->once())->method('save')->with($order);

        $transition = new CanceledTransition($repository);
        $transition->apply($order, []);
    }
}
