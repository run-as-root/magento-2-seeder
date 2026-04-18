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
        $handler->method('create')->willReturnCallback(function () use (&$calls): void {
            $calls++;
            if ($calls === 2) {
                throw new \RuntimeException('boom');
            }
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
}
