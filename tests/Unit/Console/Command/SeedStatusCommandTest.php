<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Console\Command;

use RunAsRoot\Seeder\Console\Command\SeedStatusCommand;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedStatusCommandTest extends TestCase
{
    public function test_prints_counts_for_each_seeded_entity_type(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);

        // One fetchOne per label (7 total) in order.
        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(42, 5, 10, 12, 2, 1, 100);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $command = new SeedStatusCommand($resource);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Seeded entities in database', $display);
        $this->assertStringContainsString('Products (SEED-*)', $display);
        $this->assertStringContainsString('42', $display);
        $this->assertStringContainsString('Categories (non-root)', $display);
        $this->assertStringContainsString('Customers', $display);
        $this->assertStringContainsString('Orders', $display);
        $this->assertStringContainsString('CMS pages (seed-*)', $display);
        $this->assertStringContainsString('CMS blocks (seed-*)', $display);
        $this->assertStringContainsString('Reviews on SEED products', $display);
        $this->assertStringContainsString('100', $display);
    }

    public function test_command_name_and_description(): void
    {
        $command = new SeedStatusCommand($this->createMock(ResourceConnection::class));

        $this->assertSame('db:seed:status', $command->getName());
        $this->assertStringContainsString('counts', $command->getDescription());
    }
}
