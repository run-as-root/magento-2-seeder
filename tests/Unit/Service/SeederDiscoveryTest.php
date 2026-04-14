<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\Service;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;
use DavidLambauer\Seeder\Service\EntityHandlerPool;
use DavidLambauer\Seeder\Service\GenerateRunner;
use DavidLambauer\Seeder\Service\SeederDiscovery;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

final class SeederDiscoveryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/seeder_test_' . uniqid('', true);
        mkdir($this->tempDir . '/dev/seeders', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_returns_empty_array_when_directory_does_not_exist(): void
    {
        $this->removeDirectory($this->tempDir);

        $discovery = new SeederDiscovery(
            $this->createDirectoryListMock($this->tempDir),
            $this->createMock(ObjectManagerInterface::class),
            new EntityHandlerPool([]),
            $this->createMock(GenerateRunner::class),
        );

        $this->assertSame([], $discovery->discover());
    }

    public function test_discovers_array_seeder_files(): void
    {
        file_put_contents(
            $this->tempDir . '/dev/seeders/CustomerSeeder.php',
            "<?php\nreturn ['type' => 'customer', 'data' => [['email' => 'test@test.com']]];"
        );

        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $discovery = new SeederDiscovery(
            $this->createDirectoryListMock($this->tempDir),
            $this->createMock(ObjectManagerInterface::class),
            $pool,
            $this->createMock(GenerateRunner::class),
        );

        $seeders = $discovery->discover();

        $this->assertCount(1, $seeders);
        $this->assertSame('customer', $seeders[0]->getType());
    }

    public function test_discovers_class_seeder_files(): void
    {
        $className = 'Test' . str_replace('.', '', uniqid('', true)) . 'Seeder';

        file_put_contents(
            $this->tempDir . '/dev/seeders/' . $className . '.php',
            sprintf(
                "<?php\nuse DavidLambauer\\Seeder\\Api\\SeederInterface;\n\n"
                . "class %s implements SeederInterface {\n"
                . "    public function getType(): string { return 'customer'; }\n"
                . "    public function getOrder(): int { return 10; }\n"
                . "    public function run(): void {}\n"
                . "}\n",
                $className
            )
        );

        $mockSeeder = $this->createMock(\DavidLambauer\Seeder\Api\SeederInterface::class);
        $mockSeeder->method('getType')->willReturn('customer');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('create')
            ->with($className)
            ->willReturn($mockSeeder);

        $discovery = new SeederDiscovery(
            $this->createDirectoryListMock($this->tempDir),
            $objectManager,
            new EntityHandlerPool([]),
            $this->createMock(GenerateRunner::class),
        );

        $seeders = $discovery->discover();

        $this->assertCount(1, $seeders);
        $this->assertSame('customer', $seeders[0]->getType());
    }

    public function test_discovers_namespaced_class_seeder_files(): void
    {
        $uniqueSuffix = str_replace('.', '', uniqid('', true));
        $className = 'SeederTest\\Namespaced' . $uniqueSuffix . 'Seeder';
        $shortName = 'Namespaced' . $uniqueSuffix . 'Seeder';

        file_put_contents(
            $this->tempDir . '/dev/seeders/' . $shortName . '.php',
            sprintf(
                "<?php\nnamespace SeederTest;\n\nuse DavidLambauer\\Seeder\\Api\\SeederInterface;\n\n"
                . "class %s implements SeederInterface {\n"
                . "    public function getType(): string { return 'product'; }\n"
                . "    public function getOrder(): int { return 20; }\n"
                . "    public function run(): void {}\n"
                . "}\n",
                'Namespaced' . $uniqueSuffix . 'Seeder'
            )
        );

        $mockSeeder = $this->createMock(\DavidLambauer\Seeder\Api\SeederInterface::class);
        $mockSeeder->method('getType')->willReturn('product');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('create')
            ->with($className)
            ->willReturn($mockSeeder);

        $discovery = new SeederDiscovery(
            $this->createDirectoryListMock($this->tempDir),
            $objectManager,
            new EntityHandlerPool([]),
            $this->createMock(GenerateRunner::class),
        );

        $seeders = $discovery->discover();

        $this->assertCount(1, $seeders);
        $this->assertSame('product', $seeders[0]->getType());
    }

    public function test_ignores_non_seeder_php_files(): void
    {
        file_put_contents(
            $this->tempDir . '/dev/seeders/Helper.php',
            "<?php\nclass Helper {}"
        );

        $discovery = new SeederDiscovery(
            $this->createDirectoryListMock($this->tempDir),
            $this->createMock(ObjectManagerInterface::class),
            new EntityHandlerPool([]),
            $this->createMock(GenerateRunner::class),
        );

        $seeders = $discovery->discover();

        $this->assertSame([], $seeders);
    }

    private function createDirectoryListMock(string $root): DirectoryList
    {
        $mock = $this->createMock(DirectoryList::class);
        $mock->method('getRoot')->willReturn($root);

        return $mock;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
