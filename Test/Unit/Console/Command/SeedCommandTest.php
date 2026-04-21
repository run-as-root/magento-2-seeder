<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Console\Command;

use RunAsRoot\Seeder\Console\Command\SeedCommand;
use RunAsRoot\Seeder\Service\GenerateRunConfig;
use RunAsRoot\Seeder\Service\GenerateRunner;
use RunAsRoot\Seeder\Service\ProgressReporter;
use RunAsRoot\Seeder\Service\SeederRunConfig;
use RunAsRoot\Seeder\Service\SeederRunner;
use Magento\Framework\App\State;
use Magento\Framework\Registry;
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
            $this->createMock(Registry::class),
            new ProgressReporter(),
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
            $this->createMock(Registry::class),
            new ProgressReporter(),
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
            $this->createMock(Registry::class),
            new ProgressReporter(),
        );
        $tester = new CommandTester($command);
        $tester->execute(['--fresh' => true, '--stop-on-error' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_registers_is_secure_area_flag_when_unset(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('isSecureArea')->willReturn(null);
        $registry->expects($this->once())
            ->method('register')
            ->with('isSecureArea', true);

        $runner = $this->createMock(SeederRunner::class);
        $runner->method('run')->willReturn([]);

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $this->createMock(GenerateRunner::class),
            $registry,
            new ProgressReporter(),
        );
        $tester = new CommandTester($command);
        $tester->execute(['--fresh' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_skips_is_secure_area_registration_when_already_set(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('isSecureArea')->willReturn(true);
        $registry->expects($this->never())->method('register');

        $runner = $this->createMock(SeederRunner::class);
        $runner->method('run')->willReturn([]);

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $this->createMock(GenerateRunner::class),
            $registry,
            new ProgressReporter(),
        );
        $tester = new CommandTester($command);
        $tester->execute([]);

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
            $this->createMock(Registry::class),
            new ProgressReporter(),
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
            $this->createMock(Registry::class),
            new ProgressReporter(),
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
            $this->createMock(Registry::class),
            new ProgressReporter(),
        );
        $tester = new CommandTester($command);
        $tester->execute(['--generate' => 'order:10']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('failed', $tester->getDisplay());
        $this->assertStringContainsString('Generator not found', $tester->getDisplay());
    }

    public function test_parse_generate_counts_handles_dotted_product_subtype(): void
    {
        $command = $this->makeCommand();

        $result = $this->invokeParseGenerateCounts($command, 'product:100,product.bundle:20,customer:5');

        $this->assertSame(
            ['product' => 100, 'product.bundle' => 20, 'customer' => 5],
            $result
        );
    }

    public function test_parse_generate_counts_trims_whitespace_around_keys_and_values(): void
    {
        $command = $this->makeCommand();

        $result = $this->invokeParseGenerateCounts($command, 'product : 50 ,  product.configurable : 10 ');

        $this->assertSame(
            ['product' => 50, 'product.configurable' => 10],
            $result
        );
    }

    public function test_parse_generate_counts_skips_malformed_pairs(): void
    {
        $command = $this->makeCommand();

        $result = $this->invokeParseGenerateCounts($command, 'product:100,garbage,customer:5');

        $this->assertSame(
            ['product' => 100, 'customer' => 5],
            $result
        );
    }

    public function test_empty_dev_seeders_prints_make_hint(): void
    {
        $runner = $this->createMock(SeederRunner::class);
        $runner->method('run')->willReturn([]);

        $command = new SeedCommand(
            $this->createMock(State::class),
            $runner,
            $this->createMock(GenerateRunner::class),
            $this->createMock(Registry::class),
            new ProgressReporter(),
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('db:seed:make', $tester->getDisplay());
    }

    private function makeCommand(): SeedCommand
    {
        return new SeedCommand(
            $this->createMock(State::class),
            $this->createMock(SeederRunner::class),
            $this->createMock(GenerateRunner::class),
            $this->createMock(Registry::class),
            new ProgressReporter(),
        );
    }

    /** @return array<string, int> */
    private function invokeParseGenerateCounts(SeedCommand $command, string $input): array
    {
        $reflection = new \ReflectionMethod($command, 'parseGenerateCounts');
        $reflection->setAccessible(true);

        /** @var array<string, int> $result */
        $result = $reflection->invoke($command, $input);

        return $result;
    }
}
