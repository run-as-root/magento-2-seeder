<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\DataGenerator;

use DavidLambauer\Seeder\Api\DataGeneratorInterface;
use DavidLambauer\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CategoryDataGenerator implements DataGeneratorInterface
{
    private const COMMERCE_CATEGORIES = [
        'Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Books',
        'Toys', 'Health & Beauty', 'Automotive', 'Food & Grocery', 'Office',
        'Jewelry', 'Pet Supplies', 'Music', 'Tools', 'Outdoor',
        'Baby', 'Arts & Crafts', 'Shoes', 'Furniture', 'Kitchen',
    ];

    public function getType(): string
    {
        return 'category';
    }

    public function getOrder(): int
    {
        return 10;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $name = $faker->randomElement(self::COMMERCE_CATEGORIES) . ' ' . $faker->word();

        $parentId = 2;
        $existingCategories = $registry->getAll('category');
        if ($existingCategories !== [] && $faker->boolean(40)) {
            $parent = $faker->randomElement($existingCategories);
            $parentId = $parent['id'] ?? 2;
        }

        return [
            'name' => ucwords($name),
            'is_active' => true,
            'parent_id' => $parentId,
            'description' => $faker->sentence(),
            'url_key' => $faker->unique()->slug(2),
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
}
