<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Sales\Api\Data\OrderInterface;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class NewTransition implements StateTransitionInterface
{
    public function getState(): string
    {
        return 'new';
    }

    public function apply(OrderInterface $order, array $data): void
    {
        // No-op: orders placed by CartManagementInterface::placeOrder land in state "new".
    }
}
