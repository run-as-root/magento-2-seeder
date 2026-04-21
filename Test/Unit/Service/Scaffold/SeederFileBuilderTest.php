<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service\Scaffold;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Service\Scaffold\SeederFileBuilder;

final class SeederFileBuilderTest extends TestCase
{
    public function test_builds_php_seeder_without_seed(): void
    {
        $builder = new SeederFileBuilder();

        $content = $builder->build(
            type: 'order',
            count: 100,
            locale: 'en_US',
            seed: null,
            format: 'php',
        );

        $this->assertStringContainsString("declare(strict_types=1);", $content);
        $this->assertStringContainsString("'type' => 'order'", $content);
        $this->assertStringContainsString("'count' => 100", $content);
        $this->assertStringContainsString("'locale' => 'en_US'", $content);
        $this->assertStringNotContainsString("'seed' =>", $content);
        $this->assertStringContainsString("// 'seed'", $content);
    }

    public function test_php_output_is_a_returnable_array(): void
    {
        $builder = new SeederFileBuilder();
        $content = $builder->build('order', 5, 'en_US', null, 'php');

        $path = tempnam(sys_get_temp_dir(), 'seeder-');
        file_put_contents($path, $content);

        try {
            $result = require $path;
            $this->assertSame(['type' => 'order', 'count' => 5, 'locale' => 'en_US'], $result);
        } finally {
            unlink($path);
        }
    }

    public function test_builds_json_seeder_without_seed(): void
    {
        $builder = new SeederFileBuilder();

        $content = $builder->build('order', 100, 'en_US', null, 'json');

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['type' => 'order', 'count' => 100, 'locale' => 'en_US'],
            $decoded,
        );
        $this->assertArrayNotHasKey('seed', $decoded);
    }

    public function test_builds_json_seeder_with_seed(): void
    {
        $builder = new SeederFileBuilder();
        $content = $builder->build('customer', 50, 'de_DE', 42, 'json');

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['type' => 'customer', 'count' => 50, 'locale' => 'de_DE', 'seed' => 42],
            $decoded,
        );
    }

    public function test_builds_yaml_seeder_without_seed(): void
    {
        $builder = new SeederFileBuilder();
        $content = $builder->build('order', 100, 'en_US', null, 'yaml');

        $parsed = \Symfony\Component\Yaml\Yaml::parse($content);
        $this->assertSame(
            ['type' => 'order', 'count' => 100, 'locale' => 'en_US'],
            $parsed,
        );
    }

    public function test_builds_yaml_seeder_with_seed(): void
    {
        $builder = new SeederFileBuilder();
        $content = $builder->build('product', 25, 'fr_FR', 7, 'yaml');

        $parsed = \Symfony\Component\Yaml\Yaml::parse($content);
        $this->assertSame(
            ['type' => 'product', 'count' => 25, 'locale' => 'fr_FR', 'seed' => 7],
            $parsed,
        );
    }

    public function test_rejects_unsupported_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported format/');

        (new SeederFileBuilder())->build('order', 1, 'en_US', null, 'toml');
    }
}
