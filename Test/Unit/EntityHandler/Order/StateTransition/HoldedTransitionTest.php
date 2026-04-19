<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\HoldedTransition;

final class HoldedTransitionTest extends TestCase
{
    public function test_get_state_returns_holded(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $transition = new HoldedTransition($repository);

        $this->assertSame('holded', $transition->getState());
    }

    public function test_apply_calls_hold_then_saves(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);

        $order->expects($this->once())->method('hold')->willReturnSelf();
        $repository->expects($this->once())->method('save')->with($order);

        $transition = new HoldedTransition($repository);
        $transition->apply($order, []);
    }
}
