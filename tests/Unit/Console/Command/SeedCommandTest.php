<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\Console\Command;

use DavidLambauer\Seeder\Console\Command\SeedCommand;
use DavidLambauer\Seeder\Service\SeederRunConfig;
use DavidLambauer\Seeder\Service\SeederRunner;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends TestCase
{
    public function test_runs_all_seeders_successfully(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn([
                ['type' => 'customer', 'success' => true],
            ]);

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('customer', $tester->getDisplay());
        $this->assertStringContainsString('done', $tester->getDisplay());
    }

    public function test_passes_only_filter_to_runner(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with($this->callback(function (SeederRunConfig $config): bool {
                return $config->only === ['customer', 'order']
                    && $config->exclude === []
                    && $config->fresh === false
                    && $config->stopOnError === false;
            }))
            ->willReturn([]);

        $command = new SeedCommand($this->createMock(State::class), $runner);
        $tester = new CommandTester($command);
        $tester->execute(['--only' => 'customer,order']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_passes_fresh_and_stop_on_error_flags(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->with($this->callback(function (SeederRunConfig $config): bool {
                return $config->fresh === true && $config->stopOnError === true;
            }))
            ->willReturn([]);

        $command = new SeedCommand($this->createMock(State::class), $runner);
        $tester = new CommandTester($command);
        $tester->execute(['--fresh' => true, '--stop-on-error' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_returns_failure_when_seeder_fails(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->method('run')->willReturn([
            ['type' => 'customer', 'success' => false, 'error' => 'Something broke'],
        ]);

        $command = new SeedCommand($this->createMock(State::class), $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('failed', $tester->getDisplay());
        $this->assertStringContainsString('Something broke', $tester->getDisplay());
    }
}
