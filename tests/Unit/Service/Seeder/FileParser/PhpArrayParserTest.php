<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service\Seeder\FileParser;

use RunAsRoot\Seeder\Service\Seeder\FileParser\PhpArrayParser;
use PHPUnit\Framework\TestCase;

final class PhpArrayParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/seeder_parser_test_' . uniqid('', true);
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

    public function test_supports_php_extension(): void
    {
        $parser = new PhpArrayParser();

        $this->assertTrue($parser->supports('/tmp/Foo.php'));
    }

    public function test_does_not_support_non_php_extensions(): void
    {
        $parser = new PhpArrayParser();

        $this->assertFalse($parser->supports('/tmp/Foo.json'));
        $this->assertFalse($parser->supports('/tmp/Foo.yaml'));
        $this->assertFalse($parser->supports('/tmp/Foo.yml'));
    }

    public function test_parses_array_returning_php_file(): void
    {
        $path = $this->tempDir . '/CustomerSeeder.php';
        file_put_contents(
            $path,
            "<?php\nreturn ['type' => 'customer', 'data' => [['email' => 'a@test.com']]];\n"
        );

        $result = (new PhpArrayParser())->parse($path);

        $this->assertSame('customer', $result['type']);
        $this->assertSame([['email' => 'a@test.com']], $result['data']);
    }

    public function test_parse_throws_when_file_does_not_return_array(): void
    {
        $path = $this->tempDir . '/NotArray.php';
        file_put_contents($path, "<?php\nreturn 'not an array';\n");

        $this->expectException(\RuntimeException::class);
        (new PhpArrayParser())->parse($path);
    }
}
