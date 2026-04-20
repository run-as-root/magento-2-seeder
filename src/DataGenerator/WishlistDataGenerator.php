<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class WishlistDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'wishlist';
    }

    public function getOrder(): int
    {
        return 60;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $customers = $registry->getAll('customer');
        $products = $registry->getAll('product');

        if (empty($customers)) {
            throw new \RuntimeException('wishlist requires at least one seeded customer');
        }
        if (empty($products)) {
            throw new \RuntimeException('wishlist requires at least one seeded product');
        }

        $customer = $faker->randomElement($customers);
        $itemCount = min($faker->numberBetween(1, 5), count($products));
        $picked = $faker->randomElements($products, $itemCount);

        $items = [];
        foreach ($picked as $product) {
            $items[] = [
                'product_id' => (int) $product['id'],
                'qty' => 1,
            ];
        }

        return [
            'customer_id' => (int) $customer['id'],
            'shared' => 0,
            'items' => $items,
        ];
    }

    public function getDependencies(): array
    {
        return ['customer', 'product'];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return $dependencyType === 'customer' ? $selfCount : 0;
    }
}
