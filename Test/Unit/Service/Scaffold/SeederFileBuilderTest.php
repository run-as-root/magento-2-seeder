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
}
