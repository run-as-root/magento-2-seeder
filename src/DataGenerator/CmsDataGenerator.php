<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CmsDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'cms';
    }

    public function getOrder(): int
    {
        return 50;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $cmsType = $faker->randomElement(['page', 'block']);
        $title = ucwords($faker->words($faker->numberBetween(2, 5), true));

        $paragraphs = $faker->paragraphs($faker->numberBetween(2, 5));
        $content = '<h1>' . $faker->sentence() . '</h1>';
        foreach ($paragraphs as $paragraph) {
            $content .= '<p>' . $paragraph . '</p>';
        }

        return [
            'cms_type' => $cmsType,
            'identifier' => 'seed-' . $faker->unique()->slug(2),
            'title' => $title,
            'content' => $content,
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
