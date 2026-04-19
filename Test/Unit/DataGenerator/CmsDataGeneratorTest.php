<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CmsDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CmsDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_cms(): void
    {
        $this->assertSame('cms', (new CmsDataGenerator())->getType());
    }

    public function test_get_order_returns_50(): void
    {
        $this->assertSame(50, (new CmsDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_empty(): void
    {
        $this->assertSame([], (new CmsDataGenerator())->getDependencies());
    }

    public function test_generate_returns_valid_cms_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new CmsDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('cms_type', $data);
        $this->assertContains($data['cms_type'], ['page', 'block']);
        $this->assertArrayHasKey('identifier', $data);
        $this->assertStringStartsWith('seed-', $data['identifier']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('content', $data);
        $this->assertStringContainsString('<', $data['content']);
    }
}
