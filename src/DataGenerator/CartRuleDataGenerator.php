<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CartRuleDataGenerator implements DataGeneratorInterface
{
    private const ACTION_WEIGHTS = [
        'by_percent' => 60,
        'by_fixed' => 30,
        'free_shipping' => 10,
    ];

    private const CODE_PREFIXES = ['SAVE', 'DEAL', 'PROMO', 'BONUS'];

    public function getType(): string
    {
        return 'cart_rule';
    }

    public function getOrder(): int
    {
        return 50;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $action = $this->weightedPick($faker, self::ACTION_WEIGHTS);
        [$amount, $prefix] = match ($action) {
            'by_percent'    => [(float) $faker->numberBetween(5, 30), 'SAVE'],
            'by_fixed'      => [(float) $faker->numberBetween(5, 50), 'DEAL'],
            'free_shipping' => [0.0, 'PROMO'],
        };

        $ruleName = sprintf('Seed Rule — %s', $faker->words(2, true));
        $code = sprintf(
            '%s%d-%s',
            $prefix,
            (int) $amount,
            strtoupper($faker->bothify('??####'))
        );

        return [
            'name' => $ruleName,
            'description' => $faker->sentence(),
            'is_active' => 1,
            'website_ids' => [1],
            'customer_group_ids' => [0, 1, 2, 3],
            'from_date' => null,
            'to_date' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'),
            'uses_per_customer' => 0,
            'simple_action' => $action,
            'discount_amount' => $amount,
            'discount_qty' => 0,
            'stop_rules_processing' => 0,
            'sort_order' => 0,
            'coupon' => [
                'type' => 'specific_coupon',
                'code' => $code,
                'uses_per_coupon' => 0,
            ],
        ];
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return 0;
    }

    private function weightedPick(Generator $faker, array $weights): string
    {
        $total = array_sum($weights);
        $roll = $faker->numberBetween(1, $total);
        $acc = 0;
        foreach ($weights as $key => $weight) {
            $acc += $weight;
            if ($roll <= $acc) {
                return (string) $key;
            }
        }
        return (string) array_key_first($weights);
    }
}
