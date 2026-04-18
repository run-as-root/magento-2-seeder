<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderPool;

final class TypeBuilderPoolTest extends TestCase
{
    public function test_get_returns_registered_builder(): void
    {
        $builder = $this->createMock(TypeBuilderInterface::class);

        $pool = new TypeBuilderPool(['simple' => $builder]);

        $this->assertSame($builder, $pool->get('simple'));
    }

    public function test_has_returns_true_for_registered_and_false_for_unknown(): void
    {
        $builder = $this->createMock(TypeBuilderInterface::class);

        $pool = new TypeBuilderPool(['simple' => $builder]);

        $this->assertTrue($pool->has('simple'));
        $this->assertFalse($pool->has('bundle'));
    }

    public function test_get_throws_on_unknown_type(): void
    {
        $pool = new TypeBuilderPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No type builder registered for type: bundle');

        $pool->get('bundle');
    }

    public function test_constructor_rejects_non_builder_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TypeBuilderPool(['simple' => new \stdClass()]);
    }
}
