<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Api\SubtypeAwareInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class OrderDataGenerator implements DataGeneratorInterface, SubtypeAwareInterface
{
    private const STATE_WEIGHTS = [
        'new'        => 15,
        'processing' => 25,
        'complete'   => 40,
        'canceled'   => 10,
        'holded'     => 5,
        'closed'     => 5,
    ];

    private ?string $forcedSubtype = null;

    public function getType(): string
    {
        return 'order';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function setForcedSubtype(?string $subtype): void
    {
        $this->forcedSubtype = $subtype;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $state = $this->forcedSubtype ?? $this->weightedPick($faker, self::STATE_WEIGHTS);

        $customer = $registry->getRandom('customer');
        $products = array_values(array_filter(
            $registry->getAll('product'),
            static fn (array $p): bool => ($p['product_type'] ?? 'simple') === 'simple'
        ));

        if (empty($products)) {
            throw new \RuntimeException('No simple products available for order items');
        }

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
            'order_state' => $state,
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

    /**
     * Pick a key from a weighted map. Higher weight = more likely.
     *
     * @param array<string, int> $weights
     */
    private function weightedPick(Generator $faker, array $weights): string
    {
        $pool = [];
        foreach ($weights as $key => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $key;
            }
        }

        return $faker->randomElement($pool);
    }
}
