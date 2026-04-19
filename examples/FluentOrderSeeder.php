<?php

declare(strict_types=1);

use RunAsRoot\Seeder\Seeder;

final class FluentOrderSeeder extends Seeder
{
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
        $this->orders()
            ->count(10)
            ->with([
                'items' => [
                    ['sku' => 'TSHIRT-001', 'qty' => 2],
                ],
            ])
            ->create();
    }
}
