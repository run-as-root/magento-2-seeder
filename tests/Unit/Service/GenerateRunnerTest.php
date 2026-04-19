<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\Api\SubtypeAwareInterface;
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

    public function test_stores_handler_returned_id_in_registry(): void
    {
        $registry = new GeneratedDataRegistry();

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('category');
        $generator->method('getOrder')->willReturn(10);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['name' => 'Home & Garden']);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willReturn(42);

        $genPool = new DataGeneratorPool(['category' => $generator]);
        $handlerPool = new EntityHandlerPool(['category' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            $registry,
            $this->createMock(LoggerInterface::class),
        );

        $runner->run(new GenerateRunConfig(counts: ['category' => 1]));

        $stored = $registry->getAll('category');
        $this->assertCount(1, $stored);
        $this->assertSame(42, $stored[0]['id'], 'Handler-returned id must be stored in registry so later generators can reference it');
        $this->assertSame('Home & Garden', $stored[0]['name']);
    }

    public function test_all_iterations_failing_reports_failure_even_without_stop_on_error(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('customer');
        $generator->method('getOrder')->willReturn(30);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['email' => 'a@test.com']);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willThrowException(new \RuntimeException('invalid phone'));

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

        $this->assertFalse($results[0]['success'], 'All-failed run must report success=false');
        $this->assertSame(0, $results[0]['count']);
        $this->assertSame(3, $results[0]['failed']);
        $this->assertSame('invalid phone', $results[0]['error']);
    }

    public function test_partial_failure_reports_failed_count(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('customer');
        $generator->method('getOrder')->willReturn(30);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['email' => 'a@test.com']);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $calls = 0;
        $handler->method('create')->willReturnCallback(function () use (&$calls): int {
            $calls++;
            if ($calls === 2) {
                throw new \RuntimeException('boom');
            }

            return $calls;
        });

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

        $this->assertFalse($results[0]['success']);
        $this->assertSame(2, $results[0]['count']);
        $this->assertSame(1, $results[0]['failed']);
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

    public function test_runner_forces_subtype_on_dotted_key_via_setter(): void
    {
        $generator = $this->createMock(SubtypeAwareDataGeneratorStub::class);
        $generator->method('getType')->willReturn('product');
        $generator->method('getOrder')->willReturn(20);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['sku' => 'SEED-1']);

        $calls = [];
        $generator->expects($this->exactly(2))
            ->method('setForcedSubtype')
            ->willReturnCallback(function (?string $subtype) use (&$calls): void {
                $calls[] = $subtype;
            });

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->exactly(3))->method('create');

        $genPool = new DataGeneratorPool(['product' => $generator]);
        $handlerPool = new EntityHandlerPool(['product' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            new GeneratedDataRegistry(),
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['product.bundle' => 3]);
        $results = $runner->run($config);

        $this->assertSame(['bundle', null], $calls);
        $this->assertSame('product.bundle', $results[0]['type']);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(3, $results[0]['count']);
    }

    public function test_runner_does_not_call_setter_on_plain_type(): void
    {
        $generator = $this->createMock(SubtypeAwareDataGeneratorStub::class);
        $generator->method('getType')->willReturn('product');
        $generator->method('getOrder')->willReturn(20);
        $generator->method('getDependencies')->willReturn([]);
        $generator->method('generate')->willReturn(['sku' => 'SEED-1']);

        $generator->expects($this->never())->method('setForcedSubtype');

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->exactly(3))->method('create');

        $genPool = new DataGeneratorPool(['product' => $generator]);
        $handlerPool = new EntityHandlerPool(['product' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            new GeneratedDataRegistry(),
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['product' => 3]);
        $runner->run($config);
    }

    public function test_runner_skips_setter_on_non_subtype_aware_generator(): void
    {
        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('getType')->willReturn('product');
        $generator->method('getOrder')->willReturn(20);
        $generator->method('getDependencies')->willReturn([]);
        $generator->expects($this->exactly(3))
            ->method('generate')
            ->willReturn(['sku' => 'SEED-1']);

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->exactly(3))->method('create');

        $genPool = new DataGeneratorPool(['product' => $generator]);
        $handlerPool = new EntityHandlerPool(['product' => $handler]);
        $resolver = new DependencyResolver($genPool);

        $runner = new GenerateRunner(
            $genPool,
            $handlerPool,
            $resolver,
            new FakerFactory(),
            new GeneratedDataRegistry(),
            $this->createMock(LoggerInterface::class),
        );

        $config = new GenerateRunConfig(counts: ['product.bundle' => 3]);
        $results = $runner->run($config);

        $this->assertSame(3, $results[0]['count']);
    }
}

/**
 * Test stub combining DataGeneratorInterface and SubtypeAwareInterface so PHPUnit
 * can build a single mock that satisfies both.
 */
abstract class SubtypeAwareDataGeneratorStub implements DataGeneratorInterface, SubtypeAwareInterface
{
}
