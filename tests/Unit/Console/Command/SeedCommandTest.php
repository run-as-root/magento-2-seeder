<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\Console\Command;

use DavidLambauer\Seeder\Console\Command\SeedCommand;
use DavidLambauer\Seeder\Service\GenerateRunConfig;
use DavidLambauer\Seeder\Service\GenerateRunner;
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
            $this->createMock(GenerateRunner::class),
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

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $this->createMock(GenerateRunner::class),
        );
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

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $this->createMock(GenerateRunner::class),
        );
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

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $this->createMock(GenerateRunner::class),
        );
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('failed', $tester->getDisplay());
        $this->assertStringContainsString('Something broke', $tester->getDisplay());
    }

    public function test_generate_flag_delegates_to_generate_runner(): void
    {
        $generateRunner = $this->createMock(GenerateRunner::class);
        $generateRunner->expects($this->once())
            ->method('run')
            ->with($this->callback(function (GenerateRunConfig $config): bool {
                return $config->counts === ['order' => 100, 'customer' => 50]
                    && $config->locale === 'de_DE'
                    && $config->seed === 42
                    && $config->fresh === true
                    && $config->stopOnError === false;
            }))
            ->willReturn([
                ['type' => 'customer', 'success' => true, 'count' => 50],
                ['type' => 'order', 'success' => true, 'count' => 100],
            ]);

        $runner = $this->createMock(SeederRunner::class);
        $runner->expects($this->never())->method('run');

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $generateRunner,
        );
        $tester = new CommandTester($command);
        $tester->execute([
            '--generate' => 'order:100,customer:50',
            '--locale' => 'de_DE',
            '--seed' => '42',
            '--fresh' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Generated 50 customer(s)... done', $display);
        $this->assertStringContainsString('Generated 100 order(s)... done', $display);
        $this->assertStringContainsString('150 entities generated', $display);
        $this->assertStringContainsString('Generating with locale: de_DE', $display);
        $this->assertStringContainsString('Fresh mode: cleaning existing data...', $display);
    }

    public function test_generate_flag_returns_failure_on_error(): void
    {
        $generateRunner = $this->createMock(GenerateRunner::class);
        $generateRunner->method('run')
            ->willReturn([
                ['type' => 'order', 'success' => false, 'count' => 0, 'error' => 'Generator not found'],
            ]);

        $command = new SeedCommand(
            $this->createMock(State::class),
            $this->createMock(SeederRunner::class),
            $generateRunner,
        );
        $tester = new CommandTester($command);
        $tester->execute(['--generate' => 'order:10']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('failed', $tester->getDisplay());
        $this->assertStringContainsString('Generator not found', $tester->getDisplay());
    }
}
