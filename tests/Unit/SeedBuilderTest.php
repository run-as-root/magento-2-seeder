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
}
