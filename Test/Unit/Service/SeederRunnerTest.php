<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use Laravel\Prompts\Prompt;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\ArraySeederAdapter;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\GenerateRunConfig;
use RunAsRoot\Seeder\Service\GenerateRunner;
use RunAsRoot\Seeder\Service\SeederDiscovery;
use RunAsRoot\Seeder\Service\SeederRunConfig;
use RunAsRoot\Seeder\Service\SeederRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SeederRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        // Belt-and-suspenders: SeederRunner skips spin() when stdout isn't a
        // TTY (the usual case under PHPUnit), so Prompt::fake() is a
        // fail-safe in case any code path still reaches into prompts.
        Prompt::fake();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }

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

    public function test_sets_progress_callback_on_count_adapter(): void
    {
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
            ['type' => 'order', 'count' => 5],
            $pool,
            $generateRunner,
        );

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$adapter]);

        $runner = new SeederRunner(
            $discovery,
            $pool,
            $this->createMock(LoggerInterface::class),
        );

        $onProgress = static function (string $type, int $done, int $total): void {
        };

        $runner->run(new SeederRunConfig(), $onProgress);

        $this->assertNotNull($capturedCallback, 'Progress callback should reach GenerateRunner');
        $this->assertIsCallable($capturedCallback);
    }

    public function test_non_adapter_seeder_runs_via_spin_without_error(): void
    {
        $ran = false;
        $seeder = $this->createSeederMock('custom', 10, function () use (&$ran): void {
            $ran = true;
        });

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seeder]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        $results = $runner->run(new SeederRunConfig());

        $this->assertTrue($ran, 'spin() should execute the seeder callback');
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
    }

    public function test_non_tty_non_adapter_seeder_runs_without_spin(): void
    {
        // PHPUnit runs with stdout redirected (not a TTY), so the TTY guard
        // in SeederRunner::runWithFeedback() should bypass spin() entirely.
        // Output capture verifies no ANSI escape bytes leak through (which
        // would be the symptom of spin() rendering into a logfile in CI).
        $this->assertFalse(
            stream_isatty(STDOUT),
            'Precondition: PHPUnit should not be attached to a TTY.',
        );

        $ran = false;
        $seeder = $this->createSeederMock('custom', 10, function () use (&$ran): void {
            $ran = true;
        });

        $discovery = $this->createMock(SeederDiscovery::class);
        $discovery->method('discover')->willReturn([$seeder]);

        $runner = new SeederRunner(
            $discovery,
            new EntityHandlerPool([]),
            $this->createMock(LoggerInterface::class),
        );

        ob_start();
        $results = $runner->run(new SeederRunConfig());
        $output = (string) ob_get_clean();

        $this->assertTrue($ran, 'Seeder callback must still execute in non-TTY mode.');
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertStringNotContainsString(
            "\x1b[",
            $output,
            'Non-TTY run must not emit ANSI escape sequences.',
        );
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
