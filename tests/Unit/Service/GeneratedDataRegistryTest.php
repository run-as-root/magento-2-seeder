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
