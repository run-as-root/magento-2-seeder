<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class HoldedTransition implements StateTransitionInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getState(): string
    {
        return 'holded';
    }

    public function apply(OrderInterface $order, array $data): void
    {
        if (method_exists($order, 'hold')) {
            $order->hold();
        }
        $this->orderRepository->save($order);
    }
}
