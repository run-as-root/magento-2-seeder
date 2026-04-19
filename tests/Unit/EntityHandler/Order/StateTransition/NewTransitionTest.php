<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\NewTransition;

final class NewTransitionTest extends TestCase
{
    public function test_get_state_returns_new(): void
    {
        $transition = new NewTransition();

        $this->assertSame('new', $transition->getState());
    }

    public function test_apply_is_noop(): void
    {
        $transition = new NewTransition();
        $order = $this->createMock(OrderInterface::class);

        // No methods should be called on the order.
        $order->expects($this->never())->method($this->anything());

        $transition->apply($order, []);
    }
}
