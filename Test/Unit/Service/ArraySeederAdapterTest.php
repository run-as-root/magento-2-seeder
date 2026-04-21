<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Service\ArraySeederAdapter;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\GenerateRunConfig;
use RunAsRoot\Seeder\Service\GenerateRunner;
use PHPUnit\Framework\TestCase;

final class ArraySeederAdapterTest extends TestCase
{
    public function test_get_type_returns_configured_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool,
            null
        );

        $this->assertSame('customer', $adapter->getType());
    }

    public function test_get_order_returns_default_for_known_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool,
            null
        );

        $this->assertSame(30, $adapter->getOrder());
    }

    public function test_get_order_returns_custom_order_when_specified(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => [], 'order' => 5],
            $pool,
            null
        );

        $this->assertSame(5, $adapter->getOrder());
    }

    public function test_get_order_returns_100_for_unknown_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['custom' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'custom', 'data' => []],
            $pool,
            null
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
            ->willReturnCallback(function (array $data) use (&$callCount, $expectedData): int {
                $this->assertSame($expectedData[$callCount], $data);
                $callCount++;

                return $callCount;
            });

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => $expectedData],
            $pool,
            null
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
            $pool,
            null
        );

        $adapter->run();
    }

    public function test_run_delegates_to_generate_runner_when_count_is_set(): void
    {
        $generateRunner = $this->createMock(GenerateRunner::class);
        $generateRunner->expects($this->once())
            ->method('run')
            ->with($this->callback(function (GenerateRunConfig $config): bool {
                return $config->counts === ['order' => 100]
                    && $config->locale === 'de_DE'
                    && $config->seed === 42;
            }))
            ->willReturn([]);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->never())->method('create');
        $pool = new EntityHandlerPool(['order' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'order', 'count' => 100, 'locale' => 'de_DE', 'seed' => 42],
            $pool,
            $generateRunner
        );

        $adapter->run();
    }

    public function test_run_falls_back_to_data_when_no_generate_runner(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->once())
            ->method('create')
            ->with(['email' => 'test@test.com']);

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'count' => 10, 'data' => [['email' => 'test@test.com']]],
            $pool,
            null
        );

        $adapter->run();
    }

    public function test_forwards_progress_callback_to_generate_runner(): void
    {
        $callback = static function (string $type, int $done, int $total): void {
        };

        $capturedCallback = null;
        $generateRunner = $this->createMock(GenerateRunner::class);
        $generateRunner->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (GenerateRunConfig $config, ?callable $onProgress = null) use (&$capturedCallback): array {
                $capturedCallback = $onProgress;

                return [];
            });

        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['order' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'order', 'count' => 10],
            $pool,
            $generateRunner
        );
        $adapter->setProgressCallback($callback);
        $adapter->run();

        $this->assertNotNull($capturedCallback);
        $this->assertIsCallable($capturedCallback);
    }

    public function test_has_count_returns_true_for_count_config(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['order' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'order', 'count' => 10],
            $pool,
            $this->createMock(GenerateRunner::class)
        );

        $this->assertTrue($adapter->hasCount());
    }

    public function test_has_count_returns_false_for_data_config(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => [['email' => 'a@b.com']]],
            $pool,
            null
        );

        $this->assertFalse($adapter->hasCount());
    }
}
