# Magento 2 Database Seeder — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a Composer-installable Magento 2 module that provides Laravel-style database seeding via convention-based PHP files and `bin/magento db:seed` CLI.

**Architecture:** Hybrid Composer package / Magento 2 module (`RunAsRoot\Seeder`). Entity handlers abstract Magento service contracts. Seeders are auto-discovered from `dev/seeders/` in the Magento project root. Supports both array-based and class-based seeders.

**Tech Stack:** PHP 8.1+, Magento 2.4.x, PHPUnit 10, Symfony Console

**Design doc:** `docs/plans/2026-04-14-magento2-seeder-design.md`

---

## Task 1: Module Skeleton

**Files:**
- Create: `composer.json`
- Create: `src/registration.php`
- Create: `src/etc/module.xml`
- Create: `phpunit.xml.dist`

**Step 1: Create composer.json**

```json
{
    "name": "runasroot/module-seeder",
    "description": "Laravel-style database seeding for Magento 2",
    "type": "magento2-module",
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "magento/framework": "*",
        "magento/module-customer": "*",
        "magento/module-catalog": "*",
        "magento/module-sales": "*",
        "magento/module-quote": "*",
        "magento/module-cms": "*",
        "magento/module-checkout": "*",
        "magento/module-catalog-inventory": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "files": [
            "src/registration.php"
        ],
        "psr-4": {
            "RunAsRoot\\Seeder\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "RunAsRoot\\Seeder\\Test\\": "tests/"
        }
    }
}
```

**Step 2: Create src/registration.php**

```php
<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'RunAsRoot_Seeder',
    __DIR__
);
```

**Step 3: Create src/etc/module.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="RunAsRoot_Seeder">
        <sequence>
            <module name="Magento_Customer"/>
            <module name="Magento_Catalog"/>
            <module name="Magento_Sales"/>
            <module name="Magento_Quote"/>
            <module name="Magento_Cms"/>
        </sequence>
    </module>
</config>
```

**Step 4: Create phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**Step 5: Commit**

```bash
git add composer.json src/registration.php src/etc/module.xml phpunit.xml.dist
git commit -m "feat: add module skeleton with composer.json, registration, and phpunit config"
```

---

## Task 2: Core Interfaces & DTOs

**Files:**
- Create: `src/Api/SeederInterface.php`
- Create: `src/Api/EntityHandlerInterface.php`
- Create: `src/Service/SeederRunConfig.php`

**Step 1: Create src/Api/SeederInterface.php**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

interface SeederInterface
{
    public function getType(): string;

    public function getOrder(): int;

    public function run(): void;
}
```

**Step 2: Create src/Api/EntityHandlerInterface.php**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

interface EntityHandlerInterface
{
    public function create(array $data): void;

    public function clean(): void;

    public function getType(): string;
}
```

**Step 3: Create src/Service/SeederRunConfig.php**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

final class SeederRunConfig
{
    /**
     * @param string[] $only
     * @param string[] $exclude
     */
    public function __construct(
        public readonly array $only = [],
        public readonly array $exclude = [],
        public readonly bool $fresh = false,
        public readonly bool $stopOnError = false,
    ) {
    }
}
```

**Step 4: Commit**

```bash
git add src/Api/SeederInterface.php src/Api/EntityHandlerInterface.php src/Service/SeederRunConfig.php
git commit -m "feat: add core interfaces SeederInterface, EntityHandlerInterface, and SeederRunConfig DTO"
```

---

## Task 3: EntityHandlerPool (TDD)

**Files:**
- Create: `tests/Unit/Service/EntityHandlerPoolTest.php`
- Create: `src/Service/EntityHandlerPool.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use PHPUnit\Framework\TestCase;

final class EntityHandlerPoolTest extends TestCase
{
    public function test_get_returns_handler_for_registered_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $this->assertSame($handler, $pool->get('customer'));
    }

    public function test_get_throws_for_unregistered_type(): void
    {
        $pool = new EntityHandlerPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No entity handler registered for type: unknown');

        $pool->get('unknown');
    }

    public function test_has_returns_true_for_registered_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $this->assertTrue($pool->has('customer'));
    }

    public function test_has_returns_false_for_unregistered_type(): void
    {
        $pool = new EntityHandlerPool([]);

        $this->assertFalse($pool->has('order'));
    }

    public function test_get_all_returns_all_handlers(): void
    {
        $customerHandler = $this->createMock(EntityHandlerInterface::class);
        $productHandler = $this->createMock(EntityHandlerInterface::class);

        $pool = new EntityHandlerPool([
            'customer' => $customerHandler,
            'product' => $productHandler,
        ]);

        $this->assertCount(2, $pool->getAll());
        $this->assertSame($customerHandler, $pool->getAll()['customer']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Service/EntityHandlerPoolTest.php`
Expected: FAIL — class `EntityHandlerPool` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;

class EntityHandlerPool
{
    /** @param array<string, EntityHandlerInterface> $handlers */
    public function __construct(
        private readonly array $handlers = [],
    ) {
    }

    public function get(string $type): EntityHandlerInterface
    {
        if (!isset($this->handlers[$type])) {
            throw new \InvalidArgumentException("No entity handler registered for type: {$type}");
        }

        return $this->handlers[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /** @return array<string, EntityHandlerInterface> */
    public function getAll(): array
    {
        return $this->handlers;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/EntityHandlerPoolTest.php`
Expected: OK (5 tests, 5 assertions)

**Step 5: Commit**

```bash
git add tests/Unit/Service/EntityHandlerPoolTest.php src/Service/EntityHandlerPool.php
git commit -m "feat: add EntityHandlerPool with type-based handler registry"
```

---

## Task 4: ArraySeederAdapter (TDD)

**Files:**
- Create: `tests/Unit/Service/ArraySeederAdapterTest.php`
- Create: `src/Service/ArraySeederAdapter.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Service\ArraySeederAdapter;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use PHPUnit\Framework\TestCase;

final class ArraySeederAdapterTest extends TestCase
{
    public function test_get_type_returns_configured_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool
        );

        $this->assertSame('customer', $adapter->getType());
    }

    public function test_get_order_returns_default_for_known_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool
        );

        $this->assertSame(30, $adapter->getOrder());
    }

    public function test_get_order_returns_custom_order_when_specified(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => [], 'order' => 5],
            $pool
        );

        $this->assertSame(5, $adapter->getOrder());
    }

    public function test_get_order_returns_100_for_unknown_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['custom' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'custom', 'data' => []],
            $pool
        );

        $this->assertSame(100, $adapter->getOrder());
    }

    public function test_run_calls_create_for_each_data_item(): void
    {
        $expectedData = [
            ['email' => 'john@test.com'],
            ['email' => 'jane@test.com'],
        ];

        $handler = $this->createMock(EntityHandlerInterface::class);
        $callCount = 0;
        $handler->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (array $data) use (&$callCount, $expectedData): void {
                $this->assertSame($expectedData[$callCount], $data);
                $callCount++;
            });

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => $expectedData],
            $pool
        );

        $adapter->run();
    }

    public function test_run_does_nothing_with_empty_data(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->never())->method('create');

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool
        );

        $adapter->run();
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Service/ArraySeederAdapterTest.php`
Expected: FAIL — class `ArraySeederAdapter` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\SeederInterface;

class ArraySeederAdapter implements SeederInterface
{
    private const DEFAULT_ORDER = [
        'category' => 10,
        'product' => 20,
        'customer' => 30,
        'order' => 40,
        'cms' => 50,
    ];

    public function __construct(
        private readonly array $config,
        private readonly EntityHandlerPool $handlerPool,
    ) {
    }

    public function getType(): string
    {
        return $this->config['type'];
    }

    public function getOrder(): int
    {
        return $this->config['order'] ?? self::DEFAULT_ORDER[$this->config['type']] ?? 100;
    }

    public function run(): void
    {
        $handler = $this->handlerPool->get($this->config['type']);

        foreach ($this->config['data'] as $item) {
            $handler->create($item);
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/ArraySeederAdapterTest.php`
Expected: OK (6 tests, 6 assertions)

**Step 5: Commit**

```bash
git add tests/Unit/Service/ArraySeederAdapterTest.php src/Service/ArraySeederAdapter.php
git commit -m "feat: add ArraySeederAdapter to bridge array config to SeederInterface"
```

---

## Task 5: SeederDiscovery (TDD)

**Files:**
- Create: `tests/Unit/Service/SeederDiscoveryTest.php`
- Create: `src/Service/SeederDiscovery.php`

**Step 1: Write the failing test**

Uses temp directories with fixture files. Class-based seeder fixtures use unique names via `uniqid()` to avoid PHP class redefinition errors across tests.

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\SeederDiscovery;
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
        );

        $seeders = $discovery->discover();

        $this->assertCount(1, $seeders);
        $this->assertSame('customer', $seeders[0]->getType());
    }

    public function test_discovers_class_seeder_files(): void
    {
        $className = 'TestSeeder_' . str_replace('.', '_', uniqid('', true));

        file_put_contents(
            $this->tempDir . '/dev/seeders/' . $className . '.php',
            sprintf(
                "<?php\nuse RunAsRoot\\Seeder\\Api\\SeederInterface;\n\n"
                . "class %s implements SeederInterface {\n"
                . "    public function getType(): string { return 'customer'; }\n"
                . "    public function getOrder(): int { return 10; }\n"
                . "    public function run(): void {}\n"
                . "}\n",
                $className
            )
        );

        $mockSeeder = $this->createMock(\RunAsRoot\Seeder\Api\SeederInterface::class);
        $mockSeeder->method('getType')->willReturn('customer');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('create')
            ->with($className)
            ->willReturn($mockSeeder);

        $discovery = new SeederDiscovery(
            $this->createDirectoryListMock($this->tempDir),
            $objectManager,
            new EntityHandlerPool([]),
        );

        $seeders = $discovery->discover();

        $this->assertCount(1, $seeders);
        $this->assertSame('customer', $seeders[0]->getType());
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
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Service/SeederDiscoveryTest.php`
Expected: FAIL — class `SeederDiscovery` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\SeederInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;

class SeederDiscovery
{
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly ObjectManagerInterface $objectManager,
        private readonly EntityHandlerPool $handlerPool,
    ) {
    }

    /** @return SeederInterface[] */
    public function discover(): array
    {
        $seedersDir = $this->directoryList->getRoot() . '/dev/seeders';

        if (!is_dir($seedersDir)) {
            return [];
        }

        $files = glob($seedersDir . '/*Seeder.php');
        if ($files === false || $files === []) {
            return [];
        }

        $seeders = [];
        foreach ($files as $filePath) {
            $seeder = $this->processFile($filePath);
            if ($seeder !== null) {
                $seeders[] = $seeder;
            }
        }

        return $seeders;
    }

    private function processFile(string $filePath): ?SeederInterface
    {
        $result = include_once $filePath;

        if (is_array($result)) {
            return new ArraySeederAdapter($result, $this->handlerPool);
        }

        $className = pathinfo($filePath, PATHINFO_FILENAME);
        if (class_exists($className) && is_a($className, SeederInterface::class, true)) {
            return $this->objectManager->create($className);
        }

        return null;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/SeederDiscoveryTest.php`
Expected: OK (4 tests, 4+ assertions)

**Step 5: Commit**

```bash
git add tests/Unit/Service/SeederDiscoveryTest.php src/Service/SeederDiscovery.php
git commit -m "feat: add SeederDiscovery with convention-based file scanning from dev/seeders/"
```

---

## Task 6: SeederRunner (TDD)

**Files:**
- Create: `tests/Unit/Service/SeederRunnerTest.php`
- Create: `src/Service/SeederRunner.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\SeederDiscovery;
use RunAsRoot\Seeder\Service\SeederRunConfig;
use RunAsRoot\Seeder\Service\SeederRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SeederRunnerTest extends TestCase
{
    public function test_runs_discovered_seeders_sorted_by_order(): void
    {
        $executionOrder = [];

        $seederA = $this->createSeederMock('product', 20, function () use (&$executionOrder): void {
            $executionOrder[] = 'product';
        });
        $seederB = $this->createSeederMock('category', 10, function () use (&$executionOrder): void {
            $executionOrder[] = 'category';
        });

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seederA, $seederB]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        $results = $runner->run(new SeederRunConfig());

        $this->assertSame(['category', 'product'], $executionOrder);
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
    }

    public function test_only_filter_includes_matching_types(): void
    {
        $seederA = $this->createSeederMock('customer', 10);
        $seederB = $this->createSeederMock('product', 20);
        $seederB->expects($this->never())->method('run');

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seederA, $seederB]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        $results = $runner->run(new SeederRunConfig(only: ['customer']));

        $this->assertCount(1, $results);
        $this->assertSame('customer', $results[0]['type']);
    }

    public function test_exclude_filter_removes_matching_types(): void
    {
        $seederA = $this->createSeederMock('customer', 10);
        $seederB = $this->createSeederMock('product', 20);
        $seederA->expects($this->never())->method('run');

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seederA, $seederB]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        $results = $runner->run(new SeederRunConfig(exclude: ['customer']));

        $this->assertCount(1, $results);
        $this->assertSame('product', $results[0]['type']);
    }

    public function test_fresh_calls_clean_on_handlers_in_reverse_order(): void
    {
        $cleanOrder = [];

        $customerHandler = $this->createMock(EntityHandlerInterface::class);
        $customerHandler->expects($this->once())->method('clean')
            ->willReturnCallback(function () use (&$cleanOrder): void {
                $cleanOrder[] = 'customer';
            });

        $productHandler = $this->createMock(EntityHandlerInterface::class);
        $productHandler->expects($this->once())->method('clean')
            ->willReturnCallback(function () use (&$cleanOrder): void {
                $cleanOrder[] = 'product';
            });

        $pool = new EntityHandlerPool([
            'customer' => $customerHandler,
            'product' => $productHandler,
        ]);

        $seederA = $this->createSeederMock('customer', 10);
        $seederB = $this->createSeederMock('product', 20);

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seederA, $seederB]);

        $runner = new SeederRunner(
            $discovery,
            $pool,
            $this->createMock(LoggerInterface::class),
        );

        $runner->run(new SeederRunConfig(fresh: true));

        // Clean runs in reverse dependency order: product first, then customer
        $this->assertSame(['product', 'customer'], $cleanOrder);
    }

    public function test_stop_on_error_halts_after_first_failure(): void
    {
        $seederA = $this->createSeederMock('customer', 10);
        $seederA->method('run')->willThrowException(new \RuntimeException('Customer failed'));

        $seederB = $this->createSeederMock('product', 20);
        $seederB->expects($this->never())->method('run');

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seederA, $seederB]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        $results = $runner->run(new SeederRunConfig(stopOnError: true));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertSame('Customer failed', $results[0]['error']);
    }

    public function test_continues_on_error_by_default(): void
    {
        $seederA = $this->createSeederMock('customer', 10);
        $seederA->method('run')->willThrowException(new \RuntimeException('Customer failed'));

        $seederB = $this->createSeederMock('product', 20);

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seederA, $seederB]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        $results = $runner->run(new SeederRunConfig());

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }

    private function createSeederMock(string $type, int $order, ?\Closure $runCallback = null): SeederInterface
    {
        $seeder = $this->createMock(SeederInterface::class);
        $seeder->method('getType')->willReturn($type);
        $seeder->method('getOrder')->willReturn($order);

        if ($runCallback !== null) {
            $seeder->method('run')->willReturnCallback($runCallback);
        }

        return $seeder;
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Service/SeederRunnerTest.php`
Expected: FAIL — class `SeederRunner` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\SeederInterface;
use Psr\Log\LoggerInterface;

class SeederRunner
{
    public function __construct(
        private readonly SeederDiscovery $discovery,
        private readonly EntityHandlerPool $handlerPool,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array<array{type: string, success: bool, error?: string}> */
    public function run(SeederRunConfig $config): array
    {
        $seeders = $this->discovery->discover();
        $seeders = $this->filterSeeders($seeders, $config);

        usort($seeders, static fn (SeederInterface $a, SeederInterface $b): int => $a->getOrder() <=> $b->getOrder());

        if ($config->fresh) {
            $this->cleanData($seeders);
        }

        $results = [];
        foreach ($seeders as $seeder) {
            try {
                $seeder->run();
                $results[] = ['type' => $seeder->getType(), 'success' => true];
            } catch (\Throwable $e) {
                $results[] = ['type' => $seeder->getType(), 'success' => false, 'error' => $e->getMessage()];
                $this->logger->error('Seeder failed', [
                    'type' => $seeder->getType(),
                    'exception' => $e,
                ]);

                if ($config->stopOnError) {
                    break;
                }
            }
        }

        return $results;
    }

    /** @param SeederInterface[] $seeders */
    private function filterSeeders(array $seeders, SeederRunConfig $config): array
    {
        return array_values(array_filter(
            $seeders,
            static function (SeederInterface $seeder) use ($config): bool {
                if ($config->only !== [] && !in_array($seeder->getType(), $config->only, true)) {
                    return false;
                }

                if ($config->exclude !== [] && in_array($seeder->getType(), $config->exclude, true)) {
                    return false;
                }

                return true;
            }
        ));
    }

    /** @param SeederInterface[] $seeders */
    private function cleanData(array $seeders): void
    {
        $types = array_unique(array_map(
            static fn (SeederInterface $seeder): string => $seeder->getType(),
            $seeders
        ));

        $reversedTypes = array_reverse($types);

        foreach ($reversedTypes as $type) {
            if ($this->handlerPool->has($type)) {
                $this->handlerPool->get($type)->clean();
            }
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/SeederRunnerTest.php`
Expected: OK (6 tests, multiple assertions)

**Step 5: Commit**

```bash
git add tests/Unit/Service/SeederRunnerTest.php src/Service/SeederRunner.php
git commit -m "feat: add SeederRunner with filtering, ordering, fresh-clean, and error handling"
```

---

## Task 7: SeedCommand + di.xml

**Files:**
- Create: `tests/Unit/Console/Command/SeedCommandTest.php`
- Create: `src/Console/Command/SeedCommand.php`
- Create: `src/etc/di.xml`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Console\Command;

use RunAsRoot\Seeder\Console\Command\SeedCommand;
use RunAsRoot\Seeder\Service\SeederRunConfig;
use RunAsRoot\Seeder\Service\SeederRunner;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends TestCase
{
    public function test_runs_all_seeders_successfully(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn([
                ['type' => 'customer', 'success' => true],
            ]);

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('customer', $tester->getDisplay());
        $this->assertStringContainsString('done', $tester->getDisplay());
    }

    public function test_passes_only_filter_to_runner(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with($this->callback(function (SeederRunConfig $config): bool {
                return $config->only === ['customer', 'order']
                    && $config->exclude === []
                    && $config->fresh === false
                    && $config->stopOnError === false;
            }))
            ->willReturn([]);

        $command = new SeedCommand($this->createMock(State::class), $runner);
        $tester = new CommandTester($command);
        $tester->execute(['--only' => 'customer,order']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_passes_fresh_and_stop_on_error_flags(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with($this->callback(function (SeederRunConfig $config): bool {
                return $config->fresh === true && $config->stopOnError === true;
            }))
            ->willReturn([]);

        $command = new SeedCommand($this->createMock(State::class), $runner);
        $tester = new CommandTester($command);
        $tester->execute(['--fresh' => true, '--stop-on-error' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_returns_failure_when_seeder_fails(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->method('run')->willReturn([
            ['type' => 'customer', 'success' => false, 'error' => 'Something broke'],
        ]);

        $command = new SeedCommand($this->createMock(State::class), $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('failed', $tester->getDisplay());
        $this->assertStringContainsString('Something broke', $tester->getDisplay());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Console/Command/SeedCommandTest.php`
Expected: FAIL — class `SeedCommand` not found

**Step 3: Write SeedCommand implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Console\Command;

use RunAsRoot\Seeder\Service\SeederRunConfig;
use RunAsRoot\Seeder\Service\SeederRunner;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly SeederRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed');
        $this->setDescription('Seed the database with test data');

        $this->addOption(
            'only',
            null,
            InputOption::VALUE_REQUIRED,
            'Only run specific seeder types (comma-separated, e.g. customer,order)'
        );
        $this->addOption(
            'exclude',
            null,
            InputOption::VALUE_REQUIRED,
            'Exclude specific seeder types (comma-separated, e.g. cms)'
        );
        $this->addOption(
            'fresh',
            null,
            InputOption::VALUE_NONE,
            'Clean existing entity data before seeding'
        );
        $this->addOption(
            'stop-on-error',
            null,
            InputOption::VALUE_NONE,
            'Stop execution on first seeder error'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code already set
        }

        $config = new SeederRunConfig(
            only: $this->parseCommaSeparated($input->getOption('only')),
            exclude: $this->parseCommaSeparated($input->getOption('exclude')),
            fresh: (bool) $input->getOption('fresh'),
            stopOnError: (bool) $input->getOption('stop-on-error'),
        );

        if ($config->fresh) {
            $output->writeln('<comment>Fresh mode: cleaning existing data...</comment>');
        }

        $results = $this->runner->run($config);

        if ($results === []) {
            $output->writeln('<comment>No seeders found in dev/seeders/</comment>');

            return Command::SUCCESS;
        }

        $hasError = false;
        foreach ($results as $result) {
            if ($result['success']) {
                $output->writeln(sprintf('<info>Seeding %s... done</info>', $result['type']));
            } else {
                $hasError = true;
                $output->writeln(sprintf(
                    '<error>Seeding %s... failed: %s</error>',
                    $result['type'],
                    $result['error'] ?? 'Unknown error'
                ));
            }
        }

        $successCount = count(array_filter($results, static fn (array $r): bool => $r['success']));
        $output->writeln('');
        $output->writeln(sprintf('Done. %d seeder(s) completed.', $successCount));

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return string[] */
    private function parseCommaSeparated(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Console/Command/SeedCommandTest.php`
Expected: OK (4 tests, multiple assertions)

**Step 5: Create src/etc/di.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Entity Handler Pool -->
    <type name="RunAsRoot\Seeder\Service\EntityHandlerPool">
        <arguments>
            <argument name="handlers" xsi:type="array">
                <item name="customer" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CustomerHandler</item>
                <item name="product" xsi:type="object">RunAsRoot\Seeder\EntityHandler\ProductHandler</item>
                <item name="category" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CategoryHandler</item>
                <item name="order" xsi:type="object">RunAsRoot\Seeder\EntityHandler\OrderHandler</item>
                <item name="cms" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CmsHandler</item>
            </argument>
        </arguments>
    </type>

    <!-- CLI Command Registration -->
    <type name="Magento\Framework\Console\CommandListInterface">
        <arguments>
            <argument name="commands" xsi:type="array">
                <item name="seeder_db_seed" xsi:type="object">RunAsRoot\Seeder\Console\Command\SeedCommand</item>
            </argument>
        </arguments>
    </type>

</config>
```

**Step 6: Commit**

```bash
git add tests/Unit/Console/Command/SeedCommandTest.php src/Console/Command/SeedCommand.php src/etc/di.xml
git commit -m "feat: add db:seed CLI command with --only, --exclude, --fresh, --stop-on-error flags"
```

---

## Task 8: CustomerHandler (TDD)

**Files:**
- Create: `tests/Unit/EntityHandler/CustomerHandlerTest.php`
- Create: `src/EntityHandler/CustomerHandler.php`

**Context:** Uses `AccountManagementInterface::createAccount()` for customer creation (handles password hashing, validation, events). Uses `CustomerRepositoryInterface::getList()` + `deleteById()` for clean.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\CustomerHandler;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\CustomerSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class CustomerHandlerTest extends TestCase
{
    public function test_get_type_returns_customer(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('customer', $handler->getType());
    }

    public function test_create_uses_account_management_to_create_customer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())->method('setEmail')->with('john@test.com')->willReturnSelf();
        $customer->expects($this->once())->method('setFirstname')->with('John')->willReturnSelf();
        $customer->expects($this->once())->method('setLastname')->with('Doe')->willReturnSelf();
        $customer->expects($this->once())->method('setWebsiteId')->with(1)->willReturnSelf();
        $customer->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $customer->expects($this->once())->method('setGroupId')->with(1)->willReturnSelf();

        $factory = $this->createMock(CustomerInterfaceFactory::class);
        $factory->method('create')->willReturn($customer);

        $accountManagement = $this->createMock(AccountManagementInterface::class);
        $accountManagement->expects($this->once())
            ->method('createAccount')
            ->with($customer, 'Test1234!');

        $handler = $this->createHandler(
            accountManagement: $accountManagement,
            customerFactory: $factory,
        );

        $handler->create([
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => 'Test1234!',
        ]);
    }

    public function test_clean_deletes_all_customers(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn('42');

        $searchResults = $this->createMock(CustomerSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$customer]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getList')->with($searchCriteria)->willReturn($searchResults);
        $customerRepository->expects($this->once())->method('deleteById')->with('42');

        $handler = $this->createHandler(
            customerRepository: $customerRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createHandler(
        ?AccountManagementInterface $accountManagement = null,
        ?CustomerInterfaceFactory $customerFactory = null,
        ?CustomerRepositoryInterface $customerRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): CustomerHandler {
        return new CustomerHandler(
            $accountManagement ?? $this->createMock(AccountManagementInterface::class),
            $customerFactory ?? $this->createMock(CustomerInterfaceFactory::class),
            $customerRepository ?? $this->createMock(CustomerRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/CustomerHandlerTest.php`
Expected: FAIL — class `CustomerHandler` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CustomerHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'customer';
    }

    public function create(array $data): void
    {
        $customer = $this->customerFactory->create();
        $customer->setEmail($data['email'])
            ->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setWebsiteId($data['website_id'] ?? 1)
            ->setStoreId($data['store_id'] ?? 1)
            ->setGroupId($data['group_id'] ?? 1);

        if (!empty($data['dob'])) {
            $customer->setDob($data['dob']);
        }

        $this->accountManagement->createAccount(
            $customer,
            $data['password'] ?? null,
        );
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $customers = $this->customerRepository->getList($searchCriteria);

        foreach ($customers->getItems() as $customer) {
            $this->customerRepository->deleteById($customer->getId());
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/CustomerHandlerTest.php`
Expected: OK (3 tests, multiple assertions)

**Step 5: Commit**

```bash
git add tests/Unit/EntityHandler/CustomerHandlerTest.php src/EntityHandler/CustomerHandler.php
git commit -m "feat: add CustomerHandler using AccountManagement service contract"
```

---

## Task 9: CategoryHandler (TDD)

**Files:**
- Create: `tests/Unit/EntityHandler/CategoryHandlerTest.php`
- Create: `src/EntityHandler/CategoryHandler.php`

**Context:** Uses `CategoryRepositoryInterface::save()` for creation. Clean deletes all categories except root (ID 1) and default (ID 2). Use `CategoryRepositoryInterface::getList()` to find deletable categories.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\CategoryHandler;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class CategoryHandlerTest extends TestCase
{
    public function test_get_type_returns_category(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('category', $handler->getType());
    }

    public function test_create_saves_category_via_repository(): void
    {
        $category = $this->createMock(CategoryInterface::class);
        $category->expects($this->once())->method('setName')->with('Test Category')->willReturnSelf();
        $category->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $category->expects($this->once())->method('setParentId')->with(2)->willReturnSelf();

        $factory = $this->createMock(CategoryInterfaceFactory::class);
        $factory->method('create')->willReturn($category);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($category);

        $handler = $this->createHandler(
            categoryFactory: $factory,
            categoryRepository: $repository,
        );

        $handler->create([
            'name' => 'Test Category',
            'is_active' => true,
            'parent_id' => 2,
        ]);
    }

    public function test_clean_deletes_non_root_categories(): void
    {
        $category = $this->createMock(CategoryInterface::class);
        $category->method('getId')->willReturn(5);

        $searchResults = $this->createMock(CategorySearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$category]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $categoryList = $this->createMock(CategoryListInterface::class);
        $categoryList->method('getList')->willReturn($searchResults);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteByIdentifier')->with(5);

        $handler = $this->createHandler(
            categoryRepository: $repository,
            categoryList: $categoryList,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createHandler(
        ?CategoryInterfaceFactory $categoryFactory = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
        ?CategoryListInterface $categoryList = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): CategoryHandler {
        return new CategoryHandler(
            $categoryFactory ?? $this->createMock(CategoryInterfaceFactory::class),
            $categoryRepository ?? $this->createMock(CategoryRepositoryInterface::class),
            $categoryList ?? $this->createMock(CategoryListInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/CategoryHandlerTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CategoryHandler implements EntityHandlerInterface
{
    private const PROTECTED_CATEGORY_IDS = [1, 2];

    public function __construct(
        private readonly CategoryInterfaceFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryListInterface $categoryList,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'category';
    }

    public function create(array $data): void
    {
        $category = $this->categoryFactory->create();
        $category->setName($data['name']);
        $category->setIsActive($data['is_active'] ?? true);
        $category->setParentId($data['parent_id'] ?? 2);

        if (isset($data['description'])) {
            $category->setCustomAttribute('description', $data['description']);
        }

        if (isset($data['url_key'])) {
            $category->setCustomAttribute('url_key', $data['url_key']);
        }

        $this->categoryRepository->save($category);
    }

    public function clean(): void
    {
        $this->searchCriteriaBuilder->addFilter(
            'entity_id',
            self::PROTECTED_CATEGORY_IDS,
            'nin'
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $categories = $this->categoryList->getList($searchCriteria);

        foreach ($categories->getItems() as $category) {
            $this->categoryRepository->deleteByIdentifier($category->getId());
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/CategoryHandlerTest.php`
Expected: OK (3 tests)

**Step 5: Commit**

```bash
git add tests/Unit/EntityHandler/CategoryHandlerTest.php src/EntityHandler/CategoryHandler.php
git commit -m "feat: add CategoryHandler with protected root/default category exclusion"
```

---

## Task 10: ProductHandler (TDD)

**Files:**
- Create: `tests/Unit/EntityHandler/ProductHandlerTest.php`
- Create: `src/EntityHandler/ProductHandler.php`

**Context:** Uses `ProductRepositoryInterface::save()` for creation. Sets type, status, visibility, stock data. Consult Context7 `/magento/magento2` for `StockItemInterfaceFactory` and extension attributes if needed.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\ProductHandler;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class ProductHandlerTest extends TestCase
{
    public function test_get_type_returns_product(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('product', $handler->getType());
    }

    public function test_create_saves_simple_product(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->expects($this->once())->method('setSku')->with('TEST-001')->willReturnSelf();
        $product->expects($this->once())->method('setName')->with('Test Product')->willReturnSelf();
        $product->expects($this->once())->method('setPrice')->with(29.99)->willReturnSelf();
        $product->expects($this->once())->method('setAttributeSetId')->with(4)->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setTypeId')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();
        $product->method('setCustomAttribute')->willReturnSelf();

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($product);

        $handler = $this->createHandler(
            productFactory: $factory,
            productRepository: $repository,
        );

        $handler->create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'price' => 29.99,
        ]);
    }

    public function test_clean_deletes_all_products(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('TEST-001');

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('getList')->willReturn($searchResults);
        $repository->expects($this->once())->method('deleteById')->with('TEST-001');

        $handler = $this->createHandler(
            productRepository: $repository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createHandler(
        ?ProductInterfaceFactory $productFactory = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): ProductHandler {
        return new ProductHandler(
            $productFactory ?? $this->createMock(ProductInterfaceFactory::class),
            $productRepository ?? $this->createMock(ProductRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/ProductHandlerTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SearchCriteriaBuilder;

class ProductHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'product';
    }

    public function create(array $data): void
    {
        $product = $this->productFactory->create();
        $product->setSku($data['sku'])
            ->setName($data['name'])
            ->setPrice($data['price'])
            ->setAttributeSetId($data['attribute_set_id'] ?? 4)
            ->setStatus($data['status'] ?? Status::STATUS_ENABLED)
            ->setVisibility($data['visibility'] ?? Visibility::VISIBILITY_BOTH)
            ->setTypeId($data['type_id'] ?? Type::TYPE_SIMPLE)
            ->setWeight($data['weight'] ?? 1.0);

        if (isset($data['description'])) {
            $product->setCustomAttribute('description', $data['description']);
        }

        if (isset($data['short_description'])) {
            $product->setCustomAttribute('short_description', $data['short_description']);
        }

        if (isset($data['url_key'])) {
            $product->setCustomAttribute('url_key', $data['url_key']);
        }

        if (isset($data['category_ids'])) {
            $product->setCustomAttribute('category_ids', $data['category_ids']);
        }

        $this->productRepository->save($product);
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $products = $this->productRepository->getList($searchCriteria);

        foreach ($products->getItems() as $product) {
            $this->productRepository->deleteById($product->getSku());
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/ProductHandlerTest.php`
Expected: OK (3 tests)

**Step 5: Commit**

```bash
git add tests/Unit/EntityHandler/ProductHandlerTest.php src/EntityHandler/ProductHandler.php
git commit -m "feat: add ProductHandler with simple product creation via repository"
```

---

## Task 11: OrderHandler (TDD)

**Files:**
- Create: `tests/Unit/EntityHandler/OrderHandlerTest.php`
- Create: `src/EntityHandler/OrderHandler.php`

**Context:** This is the most complex handler. Uses the quote-to-order flow:
1. `CartManagementInterface::createEmptyCart()` — creates cart
2. `CartItemRepositoryInterface::save()` — adds items
3. `CartRepositoryInterface::get()` — loads quote to set addresses and shipping/payment
4. `CartManagementInterface::placeOrder()` — converts to order

For clean: uses `OrderRepositoryInterface::getList()` + `delete()`. FK cascades handle related tables (invoices, shipments, etc.).

Consult Context7 `/magento/magento2` for exact API signatures if compile errors occur.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\OrderHandler;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class OrderHandlerTest extends TestCase
{
    public function test_get_type_returns_order(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('order', $handler->getType());
    }

    public function test_create_places_order_through_quote_flow(): void
    {
        $cartManagement = $this->createMock(CartManagementInterface::class);
        $cartManagement->expects($this->once())
            ->method('createEmptyCart')
            ->willReturn(123);
        $cartManagement->expects($this->once())
            ->method('placeOrder')
            ->with(123);

        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('setQuoteId')->willReturnSelf();
        $cartItem->method('setSku')->willReturnSelf();
        $cartItem->method('setQty')->willReturnSelf();

        $cartItemFactory = $this->createMock(CartItemInterfaceFactory::class);
        $cartItemFactory->method('create')->willReturn($cartItem);

        $cartItemRepository = $this->createMock(CartItemRepositoryInterface::class);
        $cartItemRepository->expects($this->once())->method('save');

        $quote = $this->createMock(CartInterface::class);
        $quote->method('setCustomerEmail')->willReturnSelf();
        $quote->method('setCustomerIsGuest')->willReturnSelf();
        $quote->method('setCustomerFirstname')->willReturnSelf();
        $quote->method('setCustomerLastname')->willReturnSelf();
        $quote->method('getBillingAddress')->willReturn($this->createMock(\Magento\Quote\Api\Data\AddressInterface::class));
        $quote->method('getShippingAddress')->willReturn($this->createShippingAddressMock());
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('setPaymentMethod')->willReturnSelf();
        $quote->method('getPayment')->willReturn($this->createMock(\Magento\Quote\Model\Quote\Payment::class));

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(123)->willReturn($quote);

        $handler = $this->createHandler(
            cartManagement: $cartManagement,
            cartItemFactory: $cartItemFactory,
            cartItemRepository: $cartItemRepository,
            cartRepository: $cartRepository,
        );

        $handler->create([
            'customer_email' => 'test@test.com',
            'items' => [
                ['sku' => 'TEST-001', 'qty' => 2],
            ],
        ]);
    }

    public function test_clean_deletes_all_orders(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn('1');

        $searchResults = $this->createMock(OrderSearchResultInterface::class);
        $searchResults->method('getItems')->willReturn([$order]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('getList')->willReturn($searchResults);
        $orderRepository->expects($this->once())->method('delete')->with($order);

        $handler = $this->createHandler(
            orderRepository: $orderRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createShippingAddressMock(): \Magento\Quote\Api\Data\AddressInterface
    {
        $address = $this->createMock(\Magento\Quote\Api\Data\AddressInterface::class);
        $address->method('setCollectShippingRates')->willReturnSelf();
        $address->method('setShippingMethod')->willReturnSelf();

        return $address;
    }

    private function createHandler(
        ?CartManagementInterface $cartManagement = null,
        ?CartRepositoryInterface $cartRepository = null,
        ?CartItemInterfaceFactory $cartItemFactory = null,
        ?CartItemRepositoryInterface $cartItemRepository = null,
        ?OrderRepositoryInterface $orderRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): OrderHandler {
        return new OrderHandler(
            $cartManagement ?? $this->createMock(CartManagementInterface::class),
            $cartRepository ?? $this->createMock(CartRepositoryInterface::class),
            $cartItemFactory ?? $this->createMock(CartItemInterfaceFactory::class),
            $cartItemRepository ?? $this->createMock(CartItemRepositoryInterface::class),
            $orderRepository ?? $this->createMock(OrderRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/OrderHandlerTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartItemInterfaceFactory $cartItemFactory,
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'order';
    }

    public function create(array $data): void
    {
        $cartId = $this->cartManagement->createEmptyCart();

        foreach ($data['items'] as $itemData) {
            $cartItem = $this->cartItemFactory->create();
            $cartItem->setQuoteId($cartId)
                ->setSku($itemData['sku'])
                ->setQty($itemData['qty'] ?? 1);
            $this->cartItemRepository->save($cartItem);
        }

        $quote = $this->cartRepository->get($cartId);
        $quote->setCustomerEmail($data['customer_email'] ?? 'guest@example.com');
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerFirstname($data['firstname'] ?? 'Seed');
        $quote->setCustomerLastname($data['lastname'] ?? 'Customer');

        $addressData = [
            'firstname' => $data['firstname'] ?? 'Seed',
            'lastname' => $data['lastname'] ?? 'Customer',
            'street' => $data['street'] ?? '123 Main St',
            'city' => $data['city'] ?? 'New York',
            'region_id' => $data['region_id'] ?? 43,
            'postcode' => $data['postcode'] ?? '10001',
            'country_id' => $data['country_id'] ?? 'US',
            'telephone' => $data['telephone'] ?? '555-0100',
        ];

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->addData($addressData);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($addressData);
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->setShippingMethod($data['shipping_method'] ?? 'flatrate_flatrate');

        $quote->getPayment()->setMethod($data['payment_method'] ?? 'checkmo');
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $this->cartManagement->placeOrder($cartId);
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orders = $this->orderRepository->getList($searchCriteria);

        foreach ($orders->getItems() as $order) {
            $this->orderRepository->delete($order);
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/OrderHandlerTest.php`
Expected: OK (3 tests)

**Note:** The `create()` method uses `addData()` on quote addresses which is a model-level method (not on the interface). This is intentional — Magento's quote address interface doesn't expose individual setters for all fields. The implementer should verify this works against a real Magento instance and consult Context7 if `addData()` is unavailable on the address mock. You may need to use `$shippingAddress->setFirstname()`, `$shippingAddress->setLastname()`, etc. individually.

**Step 5: Commit**

```bash
git add tests/Unit/EntityHandler/OrderHandlerTest.php src/EntityHandler/OrderHandler.php
git commit -m "feat: add OrderHandler with quote-to-order flow"
```

---

## Task 12: CmsHandler (TDD)

**Files:**
- Create: `tests/Unit/EntityHandler/CmsHandlerTest.php`
- Create: `src/EntityHandler/CmsHandler.php`

**Context:** Supports both CMS pages and blocks. The `data` array uses a `cms_type` field (`page` or `block`). Clean only removes entities whose identifiers start with a `seed-` prefix to avoid nuking real content.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\CmsHandler;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\Data\BlockSearchResultsInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\Data\PageSearchResultsInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class CmsHandlerTest extends TestCase
{
    public function test_get_type_returns_cms(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('cms', $handler->getType());
    }

    public function test_create_saves_cms_page(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->expects($this->once())->method('setIdentifier')->with('seed-test-page')->willReturnSelf();
        $page->expects($this->once())->method('setTitle')->with('Test Page')->willReturnSelf();
        $page->expects($this->once())->method('setContent')->with('<p>Hello</p>')->willReturnSelf();
        $page->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $page->method('setStoreId')->willReturnSelf();

        $pageFactory = $this->createMock(PageInterfaceFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->expects($this->once())->method('save')->with($page);

        $handler = $this->createHandler(
            pageFactory: $pageFactory,
            pageRepository: $pageRepository,
        );

        $handler->create([
            'cms_type' => 'page',
            'identifier' => 'seed-test-page',
            'title' => 'Test Page',
            'content' => '<p>Hello</p>',
        ]);
    }

    public function test_create_saves_cms_block(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $block->expects($this->once())->method('setIdentifier')->with('seed-test-block')->willReturnSelf();
        $block->expects($this->once())->method('setTitle')->with('Test Block')->willReturnSelf();
        $block->expects($this->once())->method('setContent')->with('<p>Block</p>')->willReturnSelf();
        $block->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $block->method('setStoreId')->willReturnSelf();

        $blockFactory = $this->createMock(BlockInterfaceFactory::class);
        $blockFactory->method('create')->willReturn($block);

        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $blockRepository->expects($this->once())->method('save')->with($block);

        $handler = $this->createHandler(
            blockFactory: $blockFactory,
            blockRepository: $blockRepository,
        );

        $handler->create([
            'cms_type' => 'block',
            'identifier' => 'seed-test-block',
            'title' => 'Test Block',
            'content' => '<p>Block</p>',
        ]);
    }

    public function test_clean_deletes_only_seed_prefixed_pages(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getId')->willReturn('10');

        $searchResults = $this->createMock(PageSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$page]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->method('getList')->willReturn($searchResults);
        $pageRepository->expects($this->once())->method('deleteById')->with('10');

        $handler = $this->createHandler(
            pageRepository: $pageRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createHandler(
        ?PageInterfaceFactory $pageFactory = null,
        ?PageRepositoryInterface $pageRepository = null,
        ?BlockInterfaceFactory $blockFactory = null,
        ?BlockRepositoryInterface $blockRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): CmsHandler {
        return new CmsHandler(
            $pageFactory ?? $this->createMock(PageInterfaceFactory::class),
            $pageRepository ?? $this->createMock(PageRepositoryInterface::class),
            $blockFactory ?? $this->createMock(BlockInterfaceFactory::class),
            $blockRepository ?? $this->createMock(BlockRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/CmsHandlerTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CmsHandler implements EntityHandlerInterface
{
    private const SEED_PREFIX = 'seed-';

    public function __construct(
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'cms';
    }

    public function create(array $data): void
    {
        $cmsType = $data['cms_type'] ?? 'page';

        if ($cmsType === 'block') {
            $this->createBlock($data);
        } else {
            $this->createPage($data);
        }
    }

    public function clean(): void
    {
        $this->cleanPages();
        $this->cleanBlocks();
    }

    private function createPage(array $data): void
    {
        $page = $this->pageFactory->create();
        $page->setIdentifier($data['identifier'])
            ->setTitle($data['title'])
            ->setContent($data['content'] ?? '')
            ->setIsActive($data['is_active'] ?? true)
            ->setStoreId($data['store_id'] ?? [0]);

        $this->pageRepository->save($page);
    }

    private function createBlock(array $data): void
    {
        $block = $this->blockFactory->create();
        $block->setIdentifier($data['identifier'])
            ->setTitle($data['title'])
            ->setContent($data['content'] ?? '')
            ->setIsActive($data['is_active'] ?? true)
            ->setStoreId($data['store_id'] ?? [0]);

        $this->blockRepository->save($block);
    }

    private function cleanPages(): void
    {
        $this->searchCriteriaBuilder->addFilter('identifier', self::SEED_PREFIX . '%', 'like');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $pages = $this->pageRepository->getList($searchCriteria);

        foreach ($pages->getItems() as $page) {
            $this->pageRepository->deleteById($page->getId());
        }
    }

    private function cleanBlocks(): void
    {
        $this->searchCriteriaBuilder->addFilter('identifier', self::SEED_PREFIX . '%', 'like');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $blocks = $this->blockRepository->getList($searchCriteria);

        foreach ($blocks->getItems() as $block) {
            $this->blockRepository->deleteById($block->getId());
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/CmsHandlerTest.php`
Expected: OK (4 tests)

**Step 5: Commit**

```bash
git add tests/Unit/EntityHandler/CmsHandlerTest.php src/EntityHandler/CmsHandler.php
git commit -m "feat: add CmsHandler for pages/blocks with seed-prefix conservative clean"
```

---

## Task 13: Example Seeders + README

**Files:**
- Create: `examples/CustomerSeeder.php`
- Create: `examples/ProductSeeder.php`
- Create: `examples/CategorySeeder.php`
- Create: `examples/OrderSeeder.php` (class-based example)
- Modify: `README.md`

**Step 1: Create example array-based seeders**

`examples/CustomerSeeder.php`:
```php
<?php

declare(strict_types=1);

return [
    'type' => 'customer',
    'data' => [
        [
            'email' => 'john.doe@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => 'Test1234!',
        ],
        [
            'email' => 'jane.doe@example.com',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'password' => 'Test1234!',
        ],
    ],
];
```

`examples/CategorySeeder.php`:
```php
<?php

declare(strict_types=1);

return [
    'type' => 'category',
    'data' => [
        [
            'name' => 'Clothing',
            'is_active' => true,
            'parent_id' => 2,
        ],
        [
            'name' => 'Electronics',
            'is_active' => true,
            'parent_id' => 2,
        ],
    ],
];
```

`examples/ProductSeeder.php`:
```php
<?php

declare(strict_types=1);

return [
    'type' => 'product',
    'data' => [
        [
            'sku' => 'TSHIRT-001',
            'name' => 'Basic T-Shirt',
            'price' => 19.99,
            'description' => 'A comfortable cotton t-shirt.',
            'short_description' => 'Cotton t-shirt',
            'weight' => 0.3,
        ],
        [
            'sku' => 'LAPTOP-001',
            'name' => 'Developer Laptop',
            'price' => 1299.00,
            'description' => 'High-performance laptop for developers.',
            'short_description' => 'Dev laptop',
            'weight' => 2.0,
        ],
    ],
];
```

**Step 2: Create example class-based seeder**

`examples/MassOrderSeeder.php`:
```php
<?php

declare(strict_types=1);

use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;

class MassOrderSeeder implements SeederInterface
{
    public function __construct(
        private readonly EntityHandlerPool $handlerPool,
    ) {
    }

    public function getType(): string
    {
        return 'order';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function run(): void
    {
        $handler = $this->handlerPool->get('order');

        $skus = ['TSHIRT-001', 'LAPTOP-001'];

        for ($i = 1; $i <= 10; $i++) {
            $handler->create([
                'customer_email' => "customer{$i}@example.com",
                'items' => [
                    [
                        'sku' => $skus[array_rand($skus)],
                        'qty' => rand(1, 3),
                    ],
                ],
            ]);
        }
    }
}
```

**Step 3: Update README.md**

Write a comprehensive README with installation instructions, usage examples, and customization guide. Contents:

```markdown
# Magento 2 Database Seeder

Laravel-style database seeding for Magento 2. Define simple PHP files, run `bin/magento db:seed`, populate your dev environment.

## Installation

```bash
composer require runasroot/module-seeder --dev
bin/magento module:enable RunAsRoot_Seeder
bin/magento setup:upgrade
```

## Quick Start

1. Create a `dev/seeders/` directory in your Magento root
2. Drop seeder files in it (see examples below)
3. Run `bin/magento db:seed`

## Usage

```bash
# Run all seeders
bin/magento db:seed

# Run only specific types
bin/magento db:seed --only=customer,order

# Skip specific types
bin/magento db:seed --exclude=cms

# Wipe relevant data and re-seed
bin/magento db:seed --fresh

# Stop on first error
bin/magento db:seed --stop-on-error

# Combine flags
bin/magento db:seed --fresh --only=customer,product
```

## Seeder Formats

### Array-Based (simple)

Create a PHP file that returns an array with `type` and `data`:

```php
<?php
// dev/seeders/CustomerSeeder.php
return [
    'type' => 'customer',
    'data' => [
        ['email' => 'john@test.com', 'firstname' => 'John', 'lastname' => 'Doe', 'password' => 'Test1234!'],
        ['email' => 'jane@test.com', 'firstname' => 'Jane', 'lastname' => 'Doe', 'password' => 'Test1234!'],
    ],
];
```

### Class-Based (powerful)

For complex scenarios — loops, Faker, conditional logic:

```php
<?php
// dev/seeders/MassOrderSeeder.php
use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;

class MassOrderSeeder implements SeederInterface
{
    public function __construct(private readonly EntityHandlerPool $handlerPool) {}

    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $handler = $this->handlerPool->get('order');
        for ($i = 0; $i < 50; $i++) {
            $handler->create([
                'customer_email' => "customer{$i}@test.com",
                'items' => [['sku' => 'PRODUCT-001', 'qty' => rand(1, 5)]],
            ]);
        }
    }
}
```

## Supported Entity Types

| Type       | Array key    | What it creates                     |
|------------|-------------|-------------------------------------|
| `customer` | `customer`  | Customer accounts                   |
| `category` | `category`  | Category tree nodes                 |
| `product`  | `product`   | Simple products                     |
| `order`    | `order`     | Orders via quote-to-order flow      |
| `cms`      | `cms`       | CMS pages and blocks                |

## Default Seeding Order

1. Categories (10)
2. Products (20)
3. Customers (30)
4. Orders (40)
5. CMS (50)

Override with `'order' => 5` in array seeders or `getOrder(): int` in class seeders.

## Extending

Add custom entity handlers via `di.xml`:

```xml
<type name="RunAsRoot\Seeder\Service\EntityHandlerPool">
    <arguments>
        <argument name="handlers" xsi:type="array">
            <item name="custom_entity" xsi:type="object">Vendor\Module\Seeder\CustomEntityHandler</item>
        </argument>
    </arguments>
</type>
```

Your handler must implement `RunAsRoot\Seeder\Api\EntityHandlerInterface`.

## License

MIT
```

**Step 4: Commit**

```bash
git add examples/ README.md
git commit -m "docs: add example seeders and comprehensive README"
```

---

## Summary

| Task | Component | Files | Tests |
|------|-----------|-------|-------|
| 1 | Module Skeleton | 4 | - |
| 2 | Interfaces & DTOs | 3 | - |
| 3 | EntityHandlerPool | 1 + 1 test | 5 |
| 4 | ArraySeederAdapter | 1 + 1 test | 6 |
| 5 | SeederDiscovery | 1 + 1 test | 4 |
| 6 | SeederRunner | 1 + 1 test | 6 |
| 7 | SeedCommand + di.xml | 2 + 1 test | 4 |
| 8 | CustomerHandler | 1 + 1 test | 3 |
| 9 | CategoryHandler | 1 + 1 test | 3 |
| 10 | ProductHandler | 1 + 1 test | 3 |
| 11 | OrderHandler | 1 + 1 test | 3 |
| 12 | CmsHandler | 1 + 1 test | 4 |
| 13 | Examples + README | 5 | - |
| **Total** | | **~30 files** | **~41 tests** |

## Notes for Implementer

- **Area code:** The `SeedCommand` sets `AREA_ADMINHTML` before running seeders. Some entity operations require this.
- **OrderHandler:** The quote-to-order flow is the trickiest part. Test against a real Magento instance early. You may need to adjust address handling — consult Context7 `/magento/magento2` for exact `Quote\Address` API.
- **Stock/Inventory:** The `ProductHandler` currently doesn't set stock data. For Magento 2.4+ with MSI, stock is managed via `SourceItemsSaveInterface`. Add this as a follow-up if products need to be purchasable.
- **CMS clean safety:** The `seed-` prefix convention means users should name their seeder CMS identifiers with this prefix. Document this clearly.
- **Filesystem test fixtures:** `SeederDiscoveryTest` uses temp directories. Ensure cleanup runs even on test failures (it does via `tearDown`).
- **PHPUnit version:** Tests use PHPUnit 10 API (no `withConsecutive`, uses `willReturnCallback` for ordered assertions).
