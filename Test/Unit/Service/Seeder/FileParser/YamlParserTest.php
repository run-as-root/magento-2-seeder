<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service\Seeder\FileParser;

use RunAsRoot\Seeder\Service\Seeder\FileParser\YamlParser;
use PHPUnit\Framework\TestCase;

final class YamlParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/seeder_yaml_test_' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function test_supports_yaml_and_yml_extensions(): void
    {
        $parser = new YamlParser();

        $this->assertTrue($parser->supports('/tmp/Foo.yaml'));
        $this->assertTrue($parser->supports('/tmp/Foo.yml'));
    }

    public function test_does_not_support_non_yaml_extensions(): void
    {
        $parser = new YamlParser();

        $this->assertFalse($parser->supports('/tmp/Foo.php'));
        $this->assertFalse($parser->supports('/tmp/Foo.json'));
    }

    public function test_parses_valid_yaml(): void
    {
        $path = $this->tempDir . '/CustomerSeeder.yaml';
        file_put_contents(
            $path,
            "type: customer\norder: 30\ndata:\n  - email: yaml@test.com\n"
        );

        $result = (new YamlParser())->parse($path);

        $this->assertSame('customer', $result['type']);
        $this->assertSame(30, $result['order']);
        $this->assertSame([['email' => 'yaml@test.com']], $result['data']);
    }

    public function test_parse_throws_on_invalid_yaml(): void
    {
        $path = $this->tempDir . '/Broken.yaml';
        file_put_contents($path, "foo: bar\n  baz: : : invalid");

        $this->expectException(\RuntimeException::class);
        (new YamlParser())->parse($path);
    }

    public function test_parse_throws_when_root_is_not_an_object(): void
    {
        $path = $this->tempDir . '/Scalar.yaml';
        file_put_contents($path, "just a string\n");

        $this->expectException(\RuntimeException::class);
        (new YamlParser())->parse($path);
    }
}
