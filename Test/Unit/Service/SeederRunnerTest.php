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
