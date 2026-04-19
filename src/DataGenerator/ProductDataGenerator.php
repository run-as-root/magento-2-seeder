<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Api\SubtypeAwareInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class ProductDataGenerator implements DataGeneratorInterface, SubtypeAwareInterface
{
    private const IMAGE_URL = 'https://picsum.photos/800/800';

    /** @var array<string, int> Subtype => weight (higher = more likely). */
    private const SUBTYPE_WEIGHTS = [
        'simple'       => 70,
        'configurable' => 10,
        'bundle'       => 10,
        'grouped'      => 5,
        'downloadable' => 5,
    ];

    private ?string $forcedSubtype = null;

    public function getType(): string
    {
        return 'product';
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function setForcedSubtype(?string $subtype): void
    {
        $this->forcedSubtype = $subtype;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $subtype = $this->forcedSubtype ?? $this->weightedPick($faker, self::SUBTYPE_WEIGHTS);

        $name = ucwords($faker->words($faker->numberBetween(2, 4), true));
        $categoryIds = [];
        $categories = $registry->getAll('category');
        if ($categories !== []) {
            $categoryIds[] = $this->pickLeastUsedCategoryId($categories, $registry);
        }

        $data = [
            'product_type' => $subtype,
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

        if ($subtype === 'downloadable') {
            $data['downloadable'] = [
                'links' => [
                    [
                        'title' => 'Download 1',
                        'sample_text' => $faker->paragraph(3),
                    ],
                ],
            ];
        }

        return $data;
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

    /**
     * Return the id of the category currently holding the fewest products.
     * Deterministic tiebreaker: first match in insertion order (which is the
     * order the categories were registered).
     *
     * @param list<array<string, mixed>> $categories
     */
    private function pickLeastUsedCategoryId(array $categories, GeneratedDataRegistry $registry): int
    {
        $counts = [];
        foreach ($categories as $cat) {
            $id = (int) ($cat['id'] ?? 0);
            if ($id > 0) {
                $counts[$id] = 0;
            }
        }
        if ($counts === []) {
            return 2; // safe default (default category)
        }

        foreach ($registry->getAll('product') as $product) {
            foreach ($product['category_ids'] ?? [] as $cid) {
                $cid = (int) $cid;
                if (isset($counts[$cid])) {
                    $counts[$cid]++;
                }
            }
        }

        $min = min($counts);
        foreach ($counts as $id => $count) {
            if ($count === $min) {
                return $id;
            }
        }

        return (int) array_key_first($counts);
    }
}
