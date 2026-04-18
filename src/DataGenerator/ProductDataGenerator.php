<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class ProductDataGenerator implements DataGeneratorInterface
{
    private const IMAGE_URL = 'https://picsum.photos/800/800';

    public function getType(): string
    {
        return 'product';
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $name = ucwords($faker->words($faker->numberBetween(2, 4), true));
        $categoryIds = [];
        $categories = $registry->getAll('category');
        if ($categories !== []) {
            $category = $faker->randomElement($categories);
            $categoryIds[] = $category['id'] ?? 2;
        }

        return [
            'sku' => 'SEED-' . $faker->unique()->numberBetween(10000, 99999),
            'name' => $name,
            'price' => $faker->randomFloat(2, 5.00, 500.00),
            'description' => $faker->paragraphs(3, true),
            'short_description' => $faker->sentence(),
            'weight' => $faker->randomFloat(1, 0.1, 10.0),
            'url_key' => $faker->unique()->slug(3),
            'qty' => $faker->numberBetween(10, 500),
            'category_ids' => $categoryIds,
            'image_url' => self::IMAGE_URL,
        ];
    }

    public function getDependencies(): array
    {
        return ['category'];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        if ($dependencyType === 'category') {
            return max(1, (int) ceil($selfCount / 5));
        }

        return 0;
    }
}
