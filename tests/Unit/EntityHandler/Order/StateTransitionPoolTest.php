<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionPool;

final class StateTransitionPoolTest extends TestCase
{
    public function test_get_returns_registered_transition(): void
    {
        $transition = $this->createMock(StateTransitionInterface::class);

        $pool = new StateTransitionPool(['new' => $transition]);

        $this->assertSame($transition, $pool->get('new'));
    }

    public function test_has_returns_true_for_registered_and_false_for_unknown(): void
    {
        $transition = $this->createMock(StateTransitionInterface::class);

        $pool = new StateTransitionPool(['new' => $transition]);

        $this->assertTrue($pool->has('new'));
        $this->assertFalse($pool->has('closed'));
    }

    public function test_get_throws_on_unknown_state(): void
    {
        $pool = new StateTransitionPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No state transition registered for state: closed');

        $pool->get('closed');
    }

    public function test_constructor_rejects_non_transition_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new StateTransitionPool(['new' => new \stdClass()]);
    }
}
