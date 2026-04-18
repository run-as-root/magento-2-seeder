<?php

declare(strict_types=1);

use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;

class MassOrderSeeder implements SeederInterface
{
    public function __construct(
        private readonly EntityHandlerPool $handlerPool,
    ) {
    }

    public function getType(): string
    {
        return 'order';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function run(): void
    {
        $handler = $this->handlerPool->get('order');

        $skus = ['TSHIRT-001', 'LAPTOP-001'];

        for ($i = 1; $i <= 10; $i++) {
            $handler->create([
                'customer_email' => "customer{$i}@example.com",
                'items' => [
                    [
                        'sku' => $skus[array_rand($skus)],
                        'qty' => rand(1, 3),
                    ],
                ],
            ]);
        }
    }
}
