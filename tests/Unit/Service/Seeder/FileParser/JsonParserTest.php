<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service\Seeder\FileParser;

use RunAsRoot\Seeder\Service\Seeder\FileParser\JsonParser;
use PHPUnit\Framework\TestCase;

final class JsonParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/seeder_json_test_' . uniqid('', true);
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

    public function test_supports_json_extension(): void
    {
        $parser = new JsonParser();

        $this->assertTrue($parser->supports('/tmp/Foo.json'));
    }

    public function test_does_not_support_non_json_extensions(): void
    {
        $parser = new JsonParser();

        $this->assertFalse($parser->supports('/tmp/Foo.php'));
        $this->assertFalse($parser->supports('/tmp/Foo.yaml'));
        $this->assertFalse($parser->supports('/tmp/Foo.yml'));
    }

    public function test_parses_valid_json(): void
    {
        $path = $this->tempDir . '/CustomerSeeder.json';
        file_put_contents(
            $path,
            json_encode([
                'type' => 'customer',
                'order' => 30,
                'data' => [['email' => 'json@test.com']],
            ], JSON_PRETTY_PRINT) ?: ''
        );

        $result = (new JsonParser())->parse($path);

        $this->assertSame('customer', $result['type']);
        $this->assertSame(30, $result['order']);
        $this->assertSame([['email' => 'json@test.com']], $result['data']);
    }

    public function test_parse_throws_on_invalid_json(): void
    {
        $path = $this->tempDir . '/Broken.json';
        file_put_contents($path, '{not valid json');

        $this->expectException(\RuntimeException::class);
        (new JsonParser())->parse($path);
    }

    public function test_parse_throws_when_root_is_not_an_object(): void
    {
        $path = $this->tempDir . '/Scalar.json';
        file_put_contents($path, '"just a string"');

        $this->expectException(\RuntimeException::class);
        (new JsonParser())->parse($path);
    }
}
