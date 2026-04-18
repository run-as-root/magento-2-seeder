<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class OrderDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'order';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $customer = $registry->getRandom('customer');
        $products = $registry->getAll('product');

        $itemCount = $faker->numberBetween(1, min(5, count($products)));
        $selectedProducts = $faker->randomElements($products, $itemCount);

        $items = [];
        foreach ($selectedProducts as $product) {
            $items[] = [
                'sku' => $product['sku'],
                'qty' => $faker->numberBetween(1, 3),
            ];
        }

        $address = $customer['addresses'][0] ?? [];

        return [
            'customer_email' => $customer['email'],
            'firstname' => $customer['firstname'],
            'lastname' => $customer['lastname'],
            'street' => $address['street'][0] ?? '123 Main St',
            'city' => $address['city'] ?? 'New York',
            'region_id' => $address['region_id'] ?? 43,
            'postcode' => $address['postcode'] ?? '10001',
            'country_id' => $address['country_id'] ?? 'US',
            'telephone' => $address['telephone'] ?? '555-0100',
            'items' => $items,
        ];
    }

    public function getDependencies(): array
    {
        return ['product', 'customer'];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return match ($dependencyType) {
            'product' => max(1, (int) ceil($selfCount / 20)),
            'customer' => max(1, (int) ceil($selfCount / 5)),
            default => 0,
        };
    }
}
