<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order;

use Magento\Sales\Api\Data\OrderInterface;

interface StateTransitionInterface
{
    public function getState(): string;

    /**
     * Apply the state transition to a freshly placed order.
     * $data is the generator payload (may include per-state hints).
     */
    public function apply(OrderInterface $order, array $data): void;
}
