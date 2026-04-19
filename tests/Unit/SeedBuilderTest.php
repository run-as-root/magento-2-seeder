<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\SeedBuilder;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeedBuilderTest extends TestCase
{
    public function test_create_without_overrides_uses_generator_and_returns_ids(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->once())
            ->method('create')
            ->with(['email' => 'gen@example.com'])
            ->willReturn(42);

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn(['email' => 'gen@example.com']);

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $this->assertSame([42], $builder->create());
    }

    public function test_count_creates_n_entities_and_returns_all_ids(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->exactly(3))
            ->method('create')
            ->willReturnOnConsecutiveCalls(1, 2, 3);

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->expects($this->exactly(3))
            ->method('generate')
            ->willReturn(['k' => 'v']);

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $this->assertSame([1, 2, 3], $builder->count(3)->create());
    }

    public function test_with_merges_static_data_over_generator_output(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->once())
            ->method('create')
            ->with(['email' => 'gen@example.com', 'firstname' => 'Override'])
            ->willReturn(7);

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('generate')->willReturn(
            ['email' => 'gen@example.com', 'firstname' => 'Generated']
        );

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $this->assertSame([7], $builder->with(['firstname' => 'Override'])->create());
    }

    public function test_using_callback_is_called_per_iteration_and_overrides_with(): void
    {
        $received = [];

        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willReturnCallback(
            function (array $data) use (&$received): int {
                $received[] = $data;
                return count($received);
            }
        );

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('generate')->willReturn(['base' => 'b']);

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $builder
            ->count(2)
            ->with(['w' => 'static'])
            ->using(fn(int $i) => ['i' => $i, 'w' => 'dynamic'])
            ->create();

        $this->assertSame(
            [
                ['base' => 'b', 'w' => 'dynamic', 'i' => 0],
                ['base' => 'b', 'w' => 'dynamic', 'i' => 1],
            ],
            $received
        );
    }

    public function test_subtype_sets_and_clears_forced_subtype_on_subtype_aware_generator(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willReturn(1);

        // Anonymous class implementing both DataGeneratorInterface and SubtypeAwareInterface
        // to exercise the instanceof branch precisely.
        $generator = new class implements
            \RunAsRoot\Seeder\Api\DataGeneratorInterface,
            \RunAsRoot\Seeder\Api\SubtypeAwareInterface {
            public ?string $forced = null;
            /** @var list<string|null> */
            public array $forcedHistory = [];
            public function getType(): string { return 'product'; }
            public function getOrder(): int { return 20; }
            public function generate(
                \Faker\Generator $f,
                \RunAsRoot\Seeder\Service\GeneratedDataRegistry $r
            ): array {
                $this->forcedHistory[] = $this->forced;
                return ['sku' => 'X'];
            }
            public function getDependencies(): array { return []; }
            public function getDependencyCount(string $t, int $c): int { return 0; }
            public function setForcedSubtype(?string $subtype): void { $this->forced = $subtype; }
        };

        $builder = new SeedBuilder(
            'product',
            new EntityHandlerPool(['product' => $handler]),
            new DataGeneratorPool(['product' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $builder->subtype('bundle')->create();

        $this->assertSame(['bundle'], $generator->forcedHistory);
        $this->assertNull($generator->forced, 'subtype must be cleared after create()');
    }

    public function test_subtype_is_cleared_when_create_throws(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willThrowException(new \RuntimeException('boom'));

        $generator = new class implements
            \RunAsRoot\Seeder\Api\DataGeneratorInterface,
            \RunAsRoot\Seeder\Api\SubtypeAwareInterface {
            public ?string $forced = null;
            public function getType(): string { return 'product'; }
            public function getOrder(): int { return 20; }
            public function generate(
                \Faker\Generator $f,
                \RunAsRoot\Seeder\Service\GeneratedDataRegistry $r
            ): array { return ['sku' => 'X']; }
            public function getDependencies(): array { return []; }
            public function getDependencyCount(string $t, int $c): int { return 0; }
            public function setForcedSubtype(?string $subtype): void { $this->forced = $subtype; }
        };

        $builder = new SeedBuilder(
            'product',
            new EntityHandlerPool(['product' => $handler]),
            new DataGeneratorPool(['product' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        try {
            $builder->subtype('bundle')->create();
            $this->fail('expected RuntimeException to bubble');
        } catch (\RuntimeException) {
        }

        $this->assertNull($generator->forced, 'subtype must be cleared even when handler throws');
    }

    public function test_create_with_only_with_data_and_no_generator_writes_raw(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->once())
            ->method('create')
            ->with(['email' => 'raw@example.com'])
            ->willReturn(9);

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool([]), // no generator registered
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $this->assertSame([9], $builder->with(['email' => 'raw@example.com'])->create());
    }

    public function test_create_without_generator_and_without_with_throws(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool([]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No data generator for type "customer"');

        $builder->create();
    }

    public function test_create_writes_created_entity_with_id_to_registry(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willReturnOnConsecutiveCalls(11, 12);

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('generate')->willReturn(['email' => 'x@y.com']);

        $registry = new GeneratedDataRegistry();

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            $registry,
        );

        $builder->count(2)->create();

        $this->assertSame(
            [
                ['email' => 'x@y.com', 'id' => 11],
                ['email' => 'x@y.com', 'id' => 12],
            ],
            $registry->getAll('customer')
        );
    }

    public function test_registry_writeback_uses_base_type_for_subtyped_calls(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willReturn(77);

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('generate')->willReturn(['sku' => 'X']);

        $registry = new GeneratedDataRegistry();

        $builder = new SeedBuilder(
            'product.bundle',
            new EntityHandlerPool(['product' => $handler]),
            new DataGeneratorPool(['product' => $generator]),
            new FakerFactory(),
            $registry,
        );

        $builder->create();

        $this->assertCount(1, $registry->getAll('product'));
        $this->assertSame([], $registry->getAll('product.bundle'));
    }

    public function test_registry_is_not_written_when_handler_throws(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->method('create')->willThrowException(new \RuntimeException('boom'));

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->method('generate')->willReturn(['email' => 'x@y.com']);

        $registry = new GeneratedDataRegistry();

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            $registry,
        );

        try {
            $builder->create();
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException) {
        }

        $this->assertSame([], $registry->getAll('customer'));
    }
}
