# Faker-Powered Data Generation — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add automatic Faker-powered data generation so users can run `bin/magento db:seed --generate=order:1000` and get realistic test data with images, addresses, and all dependencies auto-resolved.

**Architecture:** DataGenerator classes produce fake data arrays. A DependencyResolver figures out what else needs generating. A GenerateRunner orchestrates: resolve deps → generate data → call existing entity handlers. Generators are DI-extensible via DataGeneratorPool.

**Tech Stack:** PHP 8.1+, fakerphp/faker, Magento 2.4.x, PHPUnit 10

**Design doc:** `docs/plans/2026-04-14-faker-generation-design.md`

---

## Task 1: Add Faker Dependency + FakerFactory (TDD)

**Files:**
- Modify: `composer.json`
- Create: `src/Service/FakerFactory.php`
- Create: `tests/Unit/Service/FakerFactoryTest.php`

**Step 1: Update composer.json**

Add `"fakerphp/faker": "^1.23"` to `require` (not require-dev — generators need it at runtime).

**Step 2: Run composer update**

```bash
composer require fakerphp/faker:^1.23
```

**Step 3: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Service\FakerFactory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

final class FakerFactoryTest extends TestCase
{
    public function test_creates_faker_with_default_locale(): void
    {
        $factory = new FakerFactory();
        $faker = $factory->create();

        $this->assertInstanceOf(Generator::class, $faker);
    }

    public function test_creates_faker_with_custom_locale(): void
    {
        $factory = new FakerFactory();
        $faker = $factory->create('de_DE');

        $this->assertInstanceOf(Generator::class, $faker);
        // Faker with de_DE locale produces German-formatted data
    }

    public function test_creates_deterministic_faker_with_seed(): void
    {
        $factory = new FakerFactory();
        $faker1 = $factory->create('en_US', 42);
        $faker2 = $factory->create('en_US', 42);

        $this->assertSame($faker1->name(), $faker2->name());
    }

    public function test_creates_random_faker_without_seed(): void
    {
        $factory = new FakerFactory();
        $faker = $factory->create('en_US', null);

        // Just verify it works — randomness means we can't assert specific values
        $this->assertNotEmpty($faker->name());
    }
}
```

**Step 4: Write implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Faker\Factory;
use Faker\Generator;

class FakerFactory
{
    public function create(string $locale = 'en_US', ?int $seed = null): Generator
    {
        $faker = Factory::create($locale);

        if ($seed !== null) {
            $faker->seed($seed);
        }

        return $faker;
    }
}
```

**Step 5: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/Service/FakerFactoryTest.php
git add composer.json composer.lock src/Service/FakerFactory.php tests/Unit/Service/FakerFactoryTest.php
git commit -m "feat: add fakerphp/faker dependency and FakerFactory service"
```

---

## Task 2: GeneratedDataRegistry (TDD)

**Files:**
- Create: `src/Service/GeneratedDataRegistry.php`
- Create: `tests/Unit/Service/GeneratedDataRegistryTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use PHPUnit\Framework\TestCase;

final class GeneratedDataRegistryTest extends TestCase
{
    public function test_add_and_get_all_returns_stored_data(): void
    {
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['email' => 'john@test.com']);
        $registry->add('customer', ['email' => 'jane@test.com']);

        $all = $registry->getAll('customer');

        $this->assertCount(2, $all);
        $this->assertSame('john@test.com', $all[0]['email']);
        $this->assertSame('jane@test.com', $all[1]['email']);
    }

    public function test_get_all_returns_empty_for_unknown_type(): void
    {
        $registry = new GeneratedDataRegistry();

        $this->assertSame([], $registry->getAll('product'));
    }

    public function test_get_random_returns_one_item(): void
    {
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['email' => 'john@test.com']);
        $registry->add('customer', ['email' => 'jane@test.com']);

        $random = $registry->getRandom('customer');

        $this->assertArrayHasKey('email', $random);
    }

    public function test_get_random_throws_when_empty(): void
    {
        $registry = new GeneratedDataRegistry();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No generated data for type: product');

        $registry->getRandom('product');
    }

    public function test_reset_clears_all_data(): void
    {
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['email' => 'john@test.com']);
        $registry->reset();

        $this->assertSame([], $registry->getAll('customer'));
    }
}
```

**Step 2: Write implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

class GeneratedDataRegistry
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $data = [];

    public function add(string $type, array $entityData): void
    {
        $this->data[$type][] = $entityData;
    }

    /** @return list<array<string, mixed>> */
    public function getAll(string $type): array
    {
        return $this->data[$type] ?? [];
    }

    /** @return array<string, mixed> */
    public function getRandom(string $type): array
    {
        $items = $this->getAll($type);

        if ($items === []) {
            throw new \RuntimeException("No generated data for type: {$type}");
        }

        return $items[array_rand($items)];
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
```

**Step 3: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/Service/GeneratedDataRegistryTest.php
git add src/Service/GeneratedDataRegistry.php tests/Unit/Service/GeneratedDataRegistryTest.php
git commit -m "feat: add GeneratedDataRegistry for cross-generator entity references"
```

---

## Task 3: DataGeneratorInterface + DataGeneratorPool (TDD)

**Files:**
- Create: `src/Api/DataGeneratorInterface.php`
- Create: `src/Service/DataGeneratorPool.php`
- Create: `tests/Unit/Service/DataGeneratorPoolTest.php`

**Step 1: Create the interface**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

interface DataGeneratorInterface
{
    public function getType(): string;

    public function getOrder(): int;

    /** Generate ONE entity's data array, compatible with EntityHandler::create() */
    public function generate(Generator $faker, GeneratedDataRegistry $registry): array;

    /** @return string[] Entity types this generator depends on */
    public function getDependencies(): array;

    /** How many of $dependencyType are needed for $selfCount of this type */
    public function getDependencyCount(string $dependencyType, int $selfCount): int;
}
```

**Step 2: Write DataGeneratorPool test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use PHPUnit\Framework\TestCase;

final class DataGeneratorPoolTest extends TestCase
{
    public function test_get_returns_generator_for_registered_type(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $pool = new DataGeneratorPool(['customer' => $generator]);

        $this->assertSame($generator, $pool->get('customer'));
    }

    public function test_get_throws_for_unregistered_type(): void
    {
        $pool = new DataGeneratorPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No data generator registered for type: unknown');

        $pool->get('unknown');
    }

    public function test_has_returns_true_for_registered_type(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $pool = new DataGeneratorPool(['customer' => $generator]);

        $this->assertTrue($pool->has('customer'));
    }

    public function test_has_returns_false_for_unregistered_type(): void
    {
        $pool = new DataGeneratorPool([]);

        $this->assertFalse($pool->has('order'));
    }

    public function test_get_all_returns_all_generators(): void
    {
        $a = $this->createMock(DataGeneratorInterface::class);
        $b = $this->createMock(DataGeneratorInterface::class);

        $pool = new DataGeneratorPool(['customer' => $a, 'product' => $b]);

        $this->assertCount(2, $pool->getAll());
    }
}
```

**Step 3: Write DataGeneratorPool implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;

class DataGeneratorPool
{
    /** @param array<string, DataGeneratorInterface> $generators */
    public function __construct(
        private readonly array $generators = [],
    ) {
    }

    public function get(string $type): DataGeneratorInterface
    {
        if (!isset($this->generators[$type])) {
            throw new \InvalidArgumentException("No data generator registered for type: {$type}");
        }

        return $this->generators[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->generators[$type]);
    }

    /** @return array<string, DataGeneratorInterface> */
    public function getAll(): array
    {
        return $this->generators;
    }
}
```

**Step 4: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/Service/DataGeneratorPoolTest.php
git add src/Api/DataGeneratorInterface.php src/Service/DataGeneratorPool.php tests/Unit/Service/DataGeneratorPoolTest.php
git commit -m "feat: add DataGeneratorInterface and DataGeneratorPool registry"
```

---

## Task 4: DependencyResolver (TDD)

**Files:**
- Create: `src/Service/DependencyResolver.php`
- Create: `tests/Unit/Service/DependencyResolverTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\DependencyResolver;
use PHPUnit\Framework\TestCase;

final class DependencyResolverTest extends TestCase
{
    public function test_resolves_single_type_with_no_dependencies(): void
    {
        $customerGen = $this->createGeneratorMock('customer', 30, []);

        $pool = new DataGeneratorPool(['customer' => $customerGen]);
        $resolver = new DependencyResolver($pool);

        $result = $resolver->resolve(['customer' => 100]);

        $this->assertSame(['customer' => 100], $result);
    }

    public function test_resolves_transitive_dependencies(): void
    {
        $categoryGen = $this->createGeneratorMock('category', 10, []);
        $productGen = $this->createGeneratorMock('product', 20, ['category']);
        $productGen->method('getDependencyCount')
            ->with('category', 50)
            ->willReturn(10);

        $orderGen = $this->createGeneratorMock('order', 40, ['product', 'customer']);
        $orderGen->method('getDependencyCount')
            ->willReturnCallback(function (string $dep, int $count): int {
                return match ($dep) {
                    'product' => (int) ceil($count / 20),
                    'customer' => (int) ceil($count / 5),
                    default => 0,
                };
            });

        $customerGen = $this->createGeneratorMock('customer', 30, []);

        $pool = new DataGeneratorPool([
            'category' => $categoryGen,
            'product' => $productGen,
            'customer' => $customerGen,
            'order' => $orderGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['order' => 1000]);

        // order:1000 → product:50, customer:200 → category:10
        $this->assertSame(1000, $result['order']);
        $this->assertSame(50, $result['product']);
        $this->assertSame(200, $result['customer']);
        $this->assertSame(10, $result['category']);
    }

    public function test_user_overrides_win_over_calculated_counts(): void
    {
        $orderGen = $this->createGeneratorMock('order', 40, ['customer']);
        $orderGen->method('getDependencyCount')
            ->with('customer', 1000)
            ->willReturn(200);

        $customerGen = $this->createGeneratorMock('customer', 30, []);

        $pool = new DataGeneratorPool([
            'customer' => $customerGen,
            'order' => $orderGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['customer' => 500, 'order' => 1000]);

        // User said 500, auto would be 200 — user wins
        $this->assertSame(500, $result['customer']);
    }

    public function test_result_is_sorted_by_generator_order(): void
    {
        $orderGen = $this->createGeneratorMock('order', 40, ['product']);
        $orderGen->method('getDependencyCount')->willReturn(10);
        $productGen = $this->createGeneratorMock('product', 20, []);

        $pool = new DataGeneratorPool([
            'order' => $orderGen,
            'product' => $productGen,
        ]);

        $resolver = new DependencyResolver($pool);
        $result = $resolver->resolve(['order' => 100]);

        $keys = array_keys($result);
        $this->assertSame('product', $keys[0]);
        $this->assertSame('order', $keys[1]);
    }

    private function createGeneratorMock(string $type, int $order, array $deps): DataGeneratorInterface
    {
        $mock = $this->createMock(DataGeneratorInterface::class);
        $mock->method('getType')->willReturn($type);
        $mock->method('getOrder')->willReturn($order);
        $mock->method('getDependencies')->willReturn($deps);

        return $mock;
    }
}
```

**Step 2: Write implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

class DependencyResolver
{
    public function __construct(
        private readonly DataGeneratorPool $generatorPool,
    ) {
    }

    /** @return array<string, int> type => count, sorted by execution order */
    public function resolve(array $requestedCounts): array
    {
        $resolved = $requestedCounts;

        $queue = array_keys($requestedCounts);
        while ($queue !== []) {
            $type = array_shift($queue);

            if (!$this->generatorPool->has($type)) {
                continue;
            }

            $generator = $this->generatorPool->get($type);

            foreach ($generator->getDependencies() as $depType) {
                $depCount = $generator->getDependencyCount($depType, $resolved[$type]);

                if (!isset($resolved[$depType])) {
                    $resolved[$depType] = $depCount;
                    $queue[] = $depType;
                } else if (!isset($requestedCounts[$depType])) {
                    // Not user-specified — take max of calculated values
                    $resolved[$depType] = max($resolved[$depType], $depCount);
                }
                // If user-specified, don't override
            }
        }

        // Sort by generator execution order
        uksort($resolved, function (string $a, string $b): int {
            $orderA = $this->generatorPool->has($a) ? $this->generatorPool->get($a)->getOrder() : 100;
            $orderB = $this->generatorPool->has($b) ? $this->generatorPool->get($b)->getOrder() : 100;

            return $orderA <=> $orderB;
        });

        return $resolved;
    }
}
```

**Step 3: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/Service/DependencyResolverTest.php
git add src/Service/DependencyResolver.php tests/Unit/Service/DependencyResolverTest.php
git commit -m "feat: add DependencyResolver for smart auto-generation of entity dependencies"
```

---

## Task 5: GenerateRunner (TDD)

**Files:**
- Create: `src/Service/GenerateRunner.php`
- Create: `src/Service/GenerateRunConfig.php`
- Create: `tests/Unit/Service/GenerateRunnerTest.php`

**Step 1: Create GenerateRunConfig DTO**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

final class GenerateRunConfig
{
    /** @param array<string, int> $counts e.g., ['order' => 1000, 'customer' => 500] */
    public function __construct(
        public readonly array $counts,
        public readonly string $locale = 'en_US',
        public readonly ?int $seed = null,
        public readonly bool $fresh = false,
        public readonly bool $stopOnError = false,
    ) {
    }
}
```

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\DependencyResolver;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use RunAsRoot\Seeder\Service\GenerateRunConfig;
use RunAsRoot\Seeder\Service\GenerateRunner;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

final class GenerateRunnerTest extends TestCase
{
    public function test_generates_and_creates_entities_via_handlers(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('customer');
        $generator->method('getOrder')->willReturn(30);
        $generator->method('getDependencies')->willReturn([]);
        $generator->expects($this->exactly(3))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(
                ['email' => 'a@test.com'],
                ['email' => 'b@test.com'],
                ['email' => 'c@test.com'],
            );

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->exactly(3))->method('create');

        $genPool = new DataGeneratorPool(['customer' => $generator]);
        $handlerPool = new EntityHandlerPool(['customer' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            new GeneratedDataRegistry(),
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['customer' => 3]);
        $results = $runner->run($config);

        $this->assertCount(1, $results);
        $this->assertSame('customer', $results[0]['type']);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(3, $results[0]['count']);
    }

    public function test_fresh_cleans_before_generating(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('customer');
        $generator->method('getOrder')->willReturn(30);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['email' => 'a@test.com']);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->once())->method('clean');

        $genPool = new DataGeneratorPool(['customer' => $generator]);
        $handlerPool = new EntityHandlerPool(['customer' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            new GeneratedDataRegistry(),
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['customer' => 1], fresh: true);
        $runner->run($config);
    }

    public function test_registers_generated_data_in_registry(): void
    {
        $registry = new GeneratedDataRegistry();
        $data = ['email' => 'a@test.com', 'firstname' => 'John'];

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('customer');
        $generator->method('getOrder')->willReturn(30);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn($data);

        $handler = $this->createMock(EntityHandlerInterface::class);

        $genPool = new DataGeneratorPool(['customer' => $generator]);
        $handlerPool = new EntityHandlerPool(['customer' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            $registry,
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['customer' => 1]);
        $runner->run($config);

        $stored = $registry->getAll('customer');
        $this->assertCount(1, $stored);
        $this->assertSame('a@test.com', $stored[0]['email']);
    }

    public function test_stop_on_error_halts_generation(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('customer');
        $generator->method('getOrder')->willReturn(30);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['email' => 'a@test.com']);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willThrowException(new \RuntimeException('DB error'));

        $genPool = new DataGeneratorPool(['customer' => $generator]);
        $handlerPool = new EntityHandlerPool(['customer' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            new GeneratedDataRegistry(),
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['customer' => 5], stopOnError: true);
        $results = $runner->run($config);

        $this->assertFalse($results[0]['success']);
    }
}
```

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Psr\Log\LoggerInterface;

class GenerateRunner
{
    public function __construct(
        private readonly DataGeneratorPool $generatorPool,
        private readonly EntityHandlerPool $handlerPool,
        private readonly DependencyResolver $resolver,
        private readonly FakerFactory $fakerFactory,
        private readonly GeneratedDataRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array<array{type: string, success: bool, count: int, error?: string}> */
    public function run(GenerateRunConfig $config): array
    {
        $this->registry->reset();

        $faker = $this->fakerFactory->create($config->locale, $config->seed);
        $resolvedCounts = $this->resolver->resolve($config->counts);

        if ($config->fresh) {
            $this->cleanTypes(array_keys($resolvedCounts));
        }

        $results = [];
        foreach ($resolvedCounts as $type => $count) {
            $results[] = $this->generateType($type, $count, $faker, $config->stopOnError);
        }

        return $results;
    }

    /** @return array{type: string, success: bool, count: int, error?: string} */
    private function generateType(string $type, int $count, \Faker\Generator $faker, bool $stopOnError): array
    {
        $generator = $this->generatorPool->get($type);
        $handler = $this->handlerPool->get($type);

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $data = $generator->generate($faker, $this->registry);
                $handler->create($data);
                $this->registry->add($type, $data);
                $created++;
            } catch (\Throwable $e) {
                $this->logger->error('Generate failed', [
                    'type' => $type,
                    'iteration' => $i,
                    'exception' => $e,
                ]);

                if ($stopOnError) {
                    return ['type' => $type, 'success' => false, 'count' => $created, 'error' => $e->getMessage()];
                }
            }
        }

        return ['type' => $type, 'success' => true, 'count' => $created];
    }

    private function cleanTypes(array $types): void
    {
        $reversed = array_reverse($types);

        foreach ($reversed as $type) {
            if ($this->handlerPool->has($type)) {
                $this->handlerPool->get($type)->clean();
            }
        }
    }
}
```

**Step 4: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/Service/GenerateRunnerTest.php
git add src/Service/GenerateRunConfig.php src/Service/GenerateRunner.php tests/Unit/Service/GenerateRunnerTest.php
git commit -m "feat: add GenerateRunner to orchestrate Faker-powered entity generation"
```

---

## Task 6: CategoryDataGenerator (TDD)

**Files:**
- Create: `src/DataGenerator/CategoryDataGenerator.php`
- Create: `tests/Unit/DataGenerator/CategoryDataGeneratorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CategoryDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CategoryDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_category(): void
    {
        $this->assertSame('category', (new CategoryDataGenerator())->getType());
    }

    public function test_get_order_returns_10(): void
    {
        $this->assertSame(10, (new CategoryDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_empty(): void
    {
        $this->assertSame([], (new CategoryDataGenerator())->getDependencies());
    }

    public function test_generate_returns_valid_category_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new CategoryDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('is_active', $data);
        $this->assertArrayHasKey('parent_id', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('url_key', $data);
        $this->assertTrue($data['is_active']);
        $this->assertNotEmpty($data['name']);
    }

    public function test_generate_nests_under_existing_categories(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 5, 'name' => 'Clothing']);

        $generator = new CategoryDataGenerator();

        // Generate many to test that some get nested under existing
        $parentIds = [];
        for ($i = 0; $i < 20; $i++) {
            $data = $generator->generate($faker, $registry);
            $parentIds[] = $data['parent_id'];
        }

        // At least some should use the existing category as parent
        $this->assertContains(5, $parentIds);
    }
}
```

**Step 2: Write implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CategoryDataGenerator implements DataGeneratorInterface
{
    private const COMMERCE_CATEGORIES = [
        'Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Books',
        'Toys', 'Health & Beauty', 'Automotive', 'Food & Grocery', 'Office',
        'Jewelry', 'Pet Supplies', 'Music', 'Tools', 'Outdoor',
        'Baby', 'Arts & Crafts', 'Shoes', 'Furniture', 'Kitchen',
    ];

    public function getType(): string
    {
        return 'category';
    }

    public function getOrder(): int
    {
        return 10;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $name = $faker->randomElement(self::COMMERCE_CATEGORIES) . ' ' . $faker->word();

        $parentId = 2; // default category
        $existingCategories = $registry->getAll('category');
        if ($existingCategories !== [] && $faker->boolean(40)) {
            $parent = $faker->randomElement($existingCategories);
            $parentId = $parent['id'] ?? 2;
        }

        return [
            'name' => ucwords($name),
            'is_active' => true,
            'parent_id' => $parentId,
            'description' => $faker->sentence(),
            'url_key' => $faker->unique()->slug(2),
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
```

**Note:** The CategoryHandler currently doesn't return the created category's ID. For the registry to track IDs, the `CategoryHandler::create()` needs to return the saved entity's ID in the data. We'll handle this by modifying handlers to capture the ID after save and having GenerateRunner merge it into the registered data. Add this to the handler enhancement in Task 11.

**Step 3: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/DataGenerator/CategoryDataGeneratorTest.php
git add src/DataGenerator/CategoryDataGenerator.php tests/Unit/DataGenerator/CategoryDataGeneratorTest.php
git commit -m "feat: add CategoryDataGenerator with commerce category names and tree nesting"
```

---

## Task 7: CustomerDataGenerator (TDD) + CustomerHandler Address Support

**Files:**
- Create: `src/DataGenerator/CustomerDataGenerator.php`
- Create: `tests/Unit/DataGenerator/CustomerDataGeneratorTest.php`
- Modify: `src/EntityHandler/CustomerHandler.php`
- Modify: `tests/Unit/EntityHandler/CustomerHandlerTest.php`

**Step 1: Write CustomerDataGenerator test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CustomerDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CustomerDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_customer(): void
    {
        $this->assertSame('customer', (new CustomerDataGenerator())->getType());
    }

    public function test_get_order_returns_30(): void
    {
        $this->assertSame(30, (new CustomerDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_empty(): void
    {
        $this->assertSame([], (new CustomerDataGenerator())->getDependencies());
    }

    public function test_generate_returns_valid_customer_data_with_addresses(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new CustomerDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('firstname', $data);
        $this->assertArrayHasKey('lastname', $data);
        $this->assertArrayHasKey('password', $data);
        $this->assertArrayHasKey('addresses', $data);
        $this->assertNotEmpty($data['addresses']);
        $this->assertStringContainsString('@', $data['email']);

        $address = $data['addresses'][0];
        $this->assertArrayHasKey('street', $address);
        $this->assertArrayHasKey('city', $address);
        $this->assertArrayHasKey('postcode', $address);
        $this->assertArrayHasKey('country_id', $address);
        $this->assertArrayHasKey('telephone', $address);
    }

    public function test_generate_produces_unique_emails(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new CustomerDataGenerator();

        $emails = [];
        for ($i = 0; $i < 50; $i++) {
            $data = $generator->generate($faker, $registry);
            $emails[] = $data['email'];
        }

        $this->assertSame(50, count(array_unique($emails)));
    }
}
```

**Step 2: Write CustomerDataGenerator implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CustomerDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'customer';
    }

    public function getOrder(): int
    {
        return 30;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $firstname = $faker->firstName();
        $lastname = $faker->lastName();

        $address = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'street' => [$faker->streetAddress()],
            'city' => $faker->city(),
            'region_id' => $faker->numberBetween(1, 65),
            'postcode' => $faker->postcode(),
            'country_id' => 'US',
            'telephone' => $faker->phoneNumber(),
            'default_billing' => true,
            'default_shipping' => true,
        ];

        return [
            'email' => $faker->unique()->safeEmail(),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => 'Test1234!',
            'dob' => $faker->date('Y-m-d', '-18 years'),
            'addresses' => [$address],
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
```

**Step 3: Enhance CustomerHandler to support addresses**

Modify `src/EntityHandler/CustomerHandler.php` — add `AddressRepositoryInterface` to constructor and handle `addresses` array after account creation:

```php
// Add to constructor:
private readonly \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
private readonly \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory,

// Add after createAccount() call in create():
$createdCustomer = $this->accountManagement->createAccount($customer, $data['password'] ?? null);

if (!empty($data['addresses'])) {
    foreach ($data['addresses'] as $addressData) {
        $address = $this->addressFactory->create();
        $address->setCustomerId($createdCustomer->getId())
            ->setFirstname($addressData['firstname'] ?? $data['firstname'])
            ->setLastname($addressData['lastname'] ?? $data['lastname'])
            ->setStreet($addressData['street'] ?? ['123 Main St'])
            ->setCity($addressData['city'] ?? 'New York')
            ->setRegionId($addressData['region_id'] ?? 43)
            ->setPostcode($addressData['postcode'] ?? '10001')
            ->setCountryId($addressData['country_id'] ?? 'US')
            ->setTelephone($addressData['telephone'] ?? '555-0100')
            ->setIsDefaultBilling($addressData['default_billing'] ?? false)
            ->setIsDefaultShipping($addressData['default_shipping'] ?? false);
        $this->addressRepository->save($address);
    }
}
```

**Step 4: Add stubs for AddressRepositoryInterface and AddressInterfaceFactory to `tests/bootstrap.php`, update CustomerHandlerTest with new constructor params.**

**Step 5: Verify all tests pass, commit**

```bash
vendor/bin/phpunit
git add src/DataGenerator/CustomerDataGenerator.php tests/Unit/DataGenerator/CustomerDataGeneratorTest.php \
  src/EntityHandler/CustomerHandler.php tests/Unit/EntityHandler/CustomerHandlerTest.php tests/bootstrap.php
git commit -m "feat: add CustomerDataGenerator with addresses and enhance CustomerHandler"
```

---

## Task 8: ImageDownloader + ProductDataGenerator (TDD) + ProductHandler Image Support

**Files:**
- Create: `src/Service/ImageDownloader.php`
- Create: `tests/Unit/Service/ImageDownloaderTest.php`
- Create: `src/DataGenerator/ProductDataGenerator.php`
- Create: `tests/Unit/DataGenerator/ProductDataGeneratorTest.php`
- Modify: `src/EntityHandler/ProductHandler.php`

**Step 1: Write ImageDownloader test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Service\ImageDownloader;
use PHPUnit\Framework\TestCase;

final class ImageDownloaderTest extends TestCase
{
    public function test_returns_null_on_invalid_url(): void
    {
        $downloader = new ImageDownloader();
        $result = $downloader->download('https://invalid.example.com/404.jpg', sys_get_temp_dir());

        $this->assertNull($result);
    }

    public function test_returns_filename_on_success(): void
    {
        // This test requires network — mark as integration or skip if offline
        if (!@file_get_contents('https://picsum.photos/10/10', false, stream_context_create(['http' => ['timeout' => 3]]))) {
            $this->markTestSkipped('picsum.photos unreachable');
        }

        $downloader = new ImageDownloader();
        $destDir = sys_get_temp_dir() . '/seeder_img_test_' . uniqid();
        mkdir($destDir, 0777, true);

        $filename = $downloader->download('https://picsum.photos/10/10', $destDir);

        $this->assertNotNull($filename);
        $this->assertFileExists($destDir . '/' . $filename);

        // Cleanup
        unlink($destDir . '/' . $filename);
        rmdir($destDir);
    }
}
```

**Step 2: Write ImageDownloader implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

class ImageDownloader
{
    public function download(string $url, string $destinationDir): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'follow_location' => true,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);
            if ($imageData === false) {
                return null;
            }

            $filename = 'seed_' . bin2hex(random_bytes(8)) . '.jpg';
            $filePath = rtrim($destinationDir, '/') . '/' . $filename;

            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0777, true);
            }

            file_put_contents($filePath, $imageData);

            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }
}
```

**Step 3: Write ProductDataGenerator test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\ProductDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class ProductDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_product(): void
    {
        $this->assertSame('product', (new ProductDataGenerator())->getType());
    }

    public function test_get_order_returns_20(): void
    {
        $this->assertSame(20, (new ProductDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_category(): void
    {
        $this->assertSame(['category'], (new ProductDataGenerator())->getDependencies());
    }

    public function test_get_dependency_count_for_category(): void
    {
        $generator = new ProductDataGenerator();

        $this->assertSame(10, $generator->getDependencyCount('category', 50));
        $this->assertSame(20, $generator->getDependencyCount('category', 100));
    }

    public function test_generate_returns_valid_product_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();

        $data = (new ProductDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('sku', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('short_description', $data);
        $this->assertArrayHasKey('weight', $data);
        $this->assertArrayHasKey('url_key', $data);
        $this->assertArrayHasKey('qty', $data);
        $this->assertArrayHasKey('image_url', $data);
        $this->assertStringStartsWith('SEED-', $data['sku']);
        $this->assertGreaterThan(0, $data['price']);
    }

    public function test_generate_assigns_category_from_registry(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('category', ['id' => 5]);
        $registry->add('category', ['id' => 8]);

        $data = (new ProductDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('category_ids', $data);
        $this->assertNotEmpty($data['category_ids']);
    }

    public function test_generate_produces_unique_skus(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $generator = new ProductDataGenerator();

        $skus = [];
        for ($i = 0; $i < 50; $i++) {
            $data = $generator->generate($faker, $registry);
            $skus[] = $data['sku'];
        }

        $this->assertSame(50, count(array_unique($skus)));
    }
}
```

**Step 4: Write ProductDataGenerator implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class ProductDataGenerator implements DataGeneratorInterface
{
    private const IMAGE_URL = 'https://picsum.photos/800/800';

    public function getType(): string
    {
        return 'product';
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $name = ucwords($faker->words($faker->numberBetween(2, 4), true));
        $categoryIds = [];
        $categories = $registry->getAll('category');
        if ($categories !== []) {
            $category = $faker->randomElement($categories);
            $categoryIds[] = $category['id'] ?? 2;
        }

        return [
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
}
```

**Step 5: Enhance ProductHandler to support images**

Modify `src/EntityHandler/ProductHandler.php` — add `ImageDownloader` and `Magento\Framework\App\Filesystem\DirectoryList` to constructor. After saving product, if `image_url` is present, download and attach via media gallery API.

The exact Magento media gallery attachment may need Context7 consultation. The key flow:
1. Download image via `ImageDownloader::download()` to `pub/media/catalog/product/import/`
2. Use `Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface::create()` to attach
3. Set image roles (base, small, thumbnail) on the product

**Step 6: Update stubs in `tests/bootstrap.php`, update existing tests for new constructor params.**

**Step 7: Verify all tests pass, commit**

```bash
vendor/bin/phpunit
git add src/Service/ImageDownloader.php tests/Unit/Service/ImageDownloaderTest.php \
  src/DataGenerator/ProductDataGenerator.php tests/Unit/DataGenerator/ProductDataGeneratorTest.php \
  src/EntityHandler/ProductHandler.php tests/Unit/EntityHandler/ProductHandlerTest.php tests/bootstrap.php
git commit -m "feat: add ProductDataGenerator with image download and enhance ProductHandler"
```

---

## Task 9: OrderDataGenerator (TDD)

**Files:**
- Create: `src/DataGenerator/OrderDataGenerator.php`
- Create: `tests/Unit/DataGenerator/OrderDataGeneratorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\OrderDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class OrderDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_order(): void
    {
        $this->assertSame('order', (new OrderDataGenerator())->getType());
    }

    public function test_get_order_returns_40(): void
    {
        $this->assertSame(40, (new OrderDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_product_and_customer(): void
    {
        $this->assertSame(['product', 'customer'], (new OrderDataGenerator())->getDependencies());
    }

    public function test_get_dependency_count_for_product(): void
    {
        $generator = new OrderDataGenerator();

        $this->assertSame(50, $generator->getDependencyCount('product', 1000));
    }

    public function test_get_dependency_count_for_customer(): void
    {
        $generator = new OrderDataGenerator();

        $this->assertSame(200, $generator->getDependencyCount('customer', 1000));
    }

    public function test_generate_returns_valid_order_data(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(42);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', [
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'addresses' => [[
                'street' => ['123 Main St'],
                'city' => 'New York',
                'region_id' => 43,
                'postcode' => '10001',
                'country_id' => 'US',
                'telephone' => '555-0100',
            ]],
        ]);
        $registry->add('product', ['sku' => 'SEED-12345']);

        $data = (new OrderDataGenerator())->generate($faker, $registry);

        $this->assertSame('john@test.com', $data['customer_email']);
        $this->assertSame('John', $data['firstname']);
        $this->assertSame('Doe', $data['lastname']);
        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items']);
        $this->assertSame('SEED-12345', $data['items'][0]['sku']);
    }
}
```

**Step 2: Write implementation**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class OrderDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'order';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $customer = $registry->getRandom('customer');
        $products = $registry->getAll('product');

        $itemCount = $faker->numberBetween(1, min(5, count($products)));
        $selectedProducts = $faker->randomElements($products, $itemCount);

        $items = [];
        foreach ($selectedProducts as $product) {
            $items[] = [
                'sku' => $product['sku'],
                'qty' => $faker->numberBetween(1, 3),
            ];
        }

        $address = $customer['addresses'][0] ?? [];

        return [
            'customer_email' => $customer['email'],
            'firstname' => $customer['firstname'],
            'lastname' => $customer['lastname'],
            'street' => $address['street'][0] ?? '123 Main St',
            'city' => $address['city'] ?? 'New York',
            'region_id' => $address['region_id'] ?? 43,
            'postcode' => $address['postcode'] ?? '10001',
            'country_id' => $address['country_id'] ?? 'US',
            'telephone' => $address['telephone'] ?? '555-0100',
            'items' => $items,
        ];
    }

    public function getDependencies(): array
    {
        return ['product', 'customer'];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return match ($dependencyType) {
            'product' => max(1, (int) ceil($selfCount / 20)),
            'customer' => max(1, (int) ceil($selfCount / 5)),
            default => 0,
        };
    }
}
```

**Step 3: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/DataGenerator/OrderDataGeneratorTest.php
git add src/DataGenerator/OrderDataGenerator.php tests/Unit/DataGenerator/OrderDataGeneratorTest.php
git commit -m "feat: add OrderDataGenerator with customer/product dependency ratios"
```

---

## Task 10: CmsDataGenerator (TDD)

**Files:**
- Create: `src/DataGenerator/CmsDataGenerator.php`
- Create: `tests/Unit/DataGenerator/CmsDataGeneratorTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Write implementation**

```php
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
```

**Step 3: Verify tests pass, commit**

```bash
vendor/bin/phpunit tests/Unit/DataGenerator/CmsDataGeneratorTest.php
git add src/DataGenerator/CmsDataGenerator.php tests/Unit/DataGenerator/CmsDataGeneratorTest.php
git commit -m "feat: add CmsDataGenerator with seed-prefixed pages and blocks"
```

---

## Task 11: SeedCommand + ArraySeederAdapter Enhancements + di.xml

**Files:**
- Modify: `src/Console/Command/SeedCommand.php`
- Modify: `src/Service/ArraySeederAdapter.php`
- Modify: `src/etc/di.xml`
- Modify: `tests/Unit/Console/Command/SeedCommandTest.php`
- Modify: `tests/Unit/Service/ArraySeederAdapterTest.php`

**Step 1: Enhance SeedCommand**

Add `--generate`, `--locale`, `--seed` options. Inject `GenerateRunner`. When `--generate` is present, parse it and route to `GenerateRunner` instead of `SeederRunner`.

Key changes to `SeedCommand`:
- Add `GenerateRunner` to constructor
- Add three new options in `configure()`
- In `execute()`: if `--generate` is set, parse `type:count` pairs, build `GenerateRunConfig`, call `GenerateRunner::run()`
- Otherwise, existing seeder file flow

```php
// New options in configure():
$this->addOption('generate', null, InputOption::VALUE_REQUIRED, 'Generate fake data (e.g. order:1000,customer:500)');
$this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Faker locale (default: en_US)', 'en_US');
$this->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Faker seed for deterministic generation');

// In execute(), before existing logic:
$generateOption = $input->getOption('generate');
if ($generateOption !== null) {
    return $this->executeGenerate($input, $output);
}

// New private method:
private function executeGenerate(InputInterface $input, OutputInterface $output): int
{
    $counts = $this->parseGenerateCounts($input->getOption('generate'));
    $config = new GenerateRunConfig(
        counts: $counts,
        locale: $input->getOption('locale'),
        seed: $input->getOption('seed') !== null ? (int) $input->getOption('seed') : null,
        fresh: (bool) $input->getOption('fresh'),
        stopOnError: (bool) $input->getOption('stop-on-error'),
    );

    $results = $this->generateRunner->run($config);

    // Display results with counts
    $hasError = false;
    foreach ($results as $result) {
        if ($result['success']) {
            $output->writeln(sprintf('<info>Generated %d %s(s)... done</info>', $result['count'], $result['type']));
        } else {
            $hasError = true;
            $output->writeln(sprintf('<error>Generating %s... failed: %s</error>', $result['type'], $result['error'] ?? 'Unknown'));
        }
    }

    $totalCount = array_sum(array_column($results, 'count'));
    $output->writeln('');
    $output->writeln(sprintf('Done. %d entities generated.', $totalCount));

    return $hasError ? Command::FAILURE : Command::SUCCESS;
}

/** @return array<string, int> */
private function parseGenerateCounts(string $value): array
{
    $counts = [];
    foreach (explode(',', $value) as $pair) {
        [$type, $count] = explode(':', trim($pair));
        $counts[trim($type)] = (int) trim($count);
    }
    return $counts;
}
```

**Step 2: Enhance ArraySeederAdapter to support `count` key**

When the config has `count` instead of `data`, the adapter delegates to `DataGeneratorPool` + `GenerateRunner`.

Modify `ArraySeederAdapter::run()`:

```php
public function run(): void
{
    if (isset($this->config['count'])) {
        $this->runGenerated();
        return;
    }

    $handler = $this->handlerPool->get($this->config['type']);
    foreach ($this->config['data'] as $item) {
        $handler->create($item);
    }
}

private function runGenerated(): void
{
    $config = new GenerateRunConfig(
        counts: [$this->config['type'] => $this->config['count']],
        locale: $this->config['locale'] ?? 'en_US',
        seed: $this->config['seed'] ?? null,
    );
    $this->generateRunner->run($config);
}
```

This requires adding `GenerateRunner` as an optional constructor dependency (injected via DI).

**Step 3: Update di.xml — wire DataGeneratorPool**

Add to `src/etc/di.xml`:

```xml
<!-- Data Generator Pool -->
<type name="RunAsRoot\Seeder\Service\DataGeneratorPool">
    <arguments>
        <argument name="generators" xsi:type="array">
            <item name="customer" xsi:type="object">RunAsRoot\Seeder\DataGenerator\CustomerDataGenerator</item>
            <item name="product" xsi:type="object">RunAsRoot\Seeder\DataGenerator\ProductDataGenerator</item>
            <item name="category" xsi:type="object">RunAsRoot\Seeder\DataGenerator\CategoryDataGenerator</item>
            <item name="order" xsi:type="object">RunAsRoot\Seeder\DataGenerator\OrderDataGenerator</item>
            <item name="cms" xsi:type="object">RunAsRoot\Seeder\DataGenerator\CmsDataGenerator</item>
        </argument>
    </arguments>
</type>
```

**Step 4: Update tests for new constructor params, add tests for --generate flag**

**Step 5: Verify all tests pass, commit**

```bash
vendor/bin/phpunit
git add src/Console/Command/SeedCommand.php src/Service/ArraySeederAdapter.php src/etc/di.xml \
  tests/Unit/Console/Command/SeedCommandTest.php tests/Unit/Service/ArraySeederAdapterTest.php tests/bootstrap.php
git commit -m "feat: add --generate, --locale, --seed CLI flags and count-based seeder support"
```

---

## Task 12: Update Examples + README

**Files:**
- Create: `examples/GenerateOrderSeeder.php` (count-based example)
- Modify: `README.md`

**Step 1: Create count-based seeder example**

```php
<?php
// examples/GenerateOrderSeeder.php
declare(strict_types=1);

return [
    'type' => 'order',
    'count' => 100,
    'locale' => 'en_US',
];
```

**Step 2: Update README.md**

Add new sections covering:
- `--generate` flag usage with examples
- `--locale` and `--seed` flags
- Count-based seeder file format
- Dependency auto-resolution explanation
- Custom data generator extension via di.xml

**Step 3: Commit**

```bash
git add examples/GenerateOrderSeeder.php README.md
git commit -m "docs: add Faker generation examples and update README with new CLI flags"
```

---

## Summary

| Task | Component | New Tests |
|------|-----------|-----------|
| 1 | FakerFactory + Faker dep | 4 |
| 2 | GeneratedDataRegistry | 5 |
| 3 | DataGeneratorInterface + Pool | 5 |
| 4 | DependencyResolver | 4 |
| 5 | GenerateRunner + Config | 4 |
| 6 | CategoryDataGenerator | 5 |
| 7 | CustomerDataGenerator + Handler addresses | 5+ |
| 8 | ImageDownloader + ProductDataGenerator + Handler images | 8+ |
| 9 | OrderDataGenerator | 6 |
| 10 | CmsDataGenerator | 4 |
| 11 | SeedCommand + ArraySeederAdapter + di.xml | 3+ |
| 12 | Examples + README | - |
| **Total** | | **~53 new tests** |

## Notes for Implementer

- **Faker is a real dependency** (in `require`, not `require-dev`). Tests can use `Faker\Factory::create()` directly — no stubs needed for Faker classes.
- **ImageDownloader tests** that hit picsum.photos should use `markTestSkipped()` if the service is unreachable.
- **CategoryHandler needs ID tracking.** After `categoryRepository->save()`, capture the returned entity's ID. The `GenerateRunner` should merge the saved entity's ID back into the data before calling `registry->add()`. The simplest approach: modify handlers to return the saved entity data, or have GenerateRunner call a handler method to extract the ID.
- **SearchCriteriaBuilder** stubs in bootstrap.php will need `setPageSize` and `setCurrentPage` methods — these were already added in the previous implementation round.
- **The `generate()` method signature** passes `Faker\Generator` and `GeneratedDataRegistry` as arguments (not via constructor) so generators are stateless and locale/seed can change per run.
- **ProductHandler image attachment** is the trickiest Magento integration. Consult Context7 `/magento/magento2` for exact `ProductAttributeMediaGalleryManagementInterface` usage. The image must be base64-encoded for the gallery entry's `content` field.
