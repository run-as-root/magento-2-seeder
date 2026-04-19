<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class CanceledTransition implements StateTransitionInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getState(): string
    {
        return 'canceled';
    }

    public function apply(OrderInterface $order, array $data): void
    {
        if (method_exists($order, 'cancel')) {
            $order->cancel();
        }
        $this->orderRepository->save($order);
    }
}
