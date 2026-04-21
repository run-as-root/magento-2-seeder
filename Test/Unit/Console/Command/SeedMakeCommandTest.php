<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Console\Command\SeedMakeCommand;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\Scaffold\SeederFileBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedMakeCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/seeder-make-' . uniqid();
        mkdir($this->workspace, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workspace)) {
            $this->removeRecursive($this->workspace);
        }
    }

    public function test_writes_php_seeder_file_with_all_flags(): void
    {
        $command = $this->makeCommand(['order', 'customer']);

        $tester = new CommandTester($command);
        $tester->setInputs([]); // no interactive input
        $exit = $tester->execute(
            [
                '--type' => 'order',
                '--count' => '25',
                '--format' => 'php',
                '--locale' => 'en_US',
                '--name' => 'QuickOrderSeeder',
            ],
            ['interactive' => false],
        );

        $this->assertSame(Command::SUCCESS, $exit);

        $file = $this->workspace . '/dev/seeders/QuickOrderSeeder.php';
        $this->assertFileExists($file);

        $config = require $file;
        $this->assertSame('order', $config['type']);
        $this->assertSame(25, $config['count']);
        $this->assertSame('en_US', $config['locale']);
    }

    public function test_rejects_unknown_type(): void
    {
        $command = $this->makeCommand(['order', 'customer']);
        $tester = new CommandTester($command);

        $exit = $tester->execute(
            ['--type' => 'banana', '--count' => '5'],
            ['interactive' => false],
        );

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('banana', $tester->getDisplay());
        $this->assertStringContainsString('order', $tester->getDisplay());
    }

    public function test_rejects_non_positive_count(): void
    {
        $command = $this->makeCommand(['order']);
        $tester = new CommandTester($command);

        $exit = $tester->execute(
            ['--type' => 'order', '--count' => '0'],
            ['interactive' => false],
        );

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('positive', $tester->getDisplay());
    }

    public function test_rejects_unknown_format(): void
    {
        $command = $this->makeCommand(['order']);
        $tester = new CommandTester($command);

        $exit = $tester->execute(
            ['--type' => 'order', '--count' => '5', '--format' => 'toml'],
            ['interactive' => false],
        );

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('toml', $tester->getDisplay());
    }

    public function test_non_interactive_without_required_flags_errors_out(): void
    {
        $command = $this->makeCommand(['order']);
        $tester = new CommandTester($command);

        $exit = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertMatchesRegularExpression('/--type.*--count/s', $tester->getDisplay());
    }

    public function test_interactive_prompts_collect_all_fields(): void
    {
        // Skipped: driving Laravel Prompts v0.3 interactive prompts through
        // CommandTester requires scripting raw terminal keystrokes (arrows,
        // ENTER, per-char typing incl. BACKSPACE to clear default values,
        // search prompt typing + selection). The keystroke scripting is
        // brittle enough that the plan explicitly authorised deferring this
        // unit-level coverage to the integration test (Task 15) and manual
        // smoke in the Warden Mage-OS env.
        //
        // The interactive branch is still exercised in the command
        // implementation (see the `\Laravel\Prompts\select()` / `text()` /
        // `search()` / `confirm()` calls in SeedMakeCommand::execute).
        $this->markTestSkipped(
            'Interactive prompts covered by integration test + manual smoke; '
            . 'see plan deviation note for Task 11.',
        );
    }

    public function test_non_interactive_refuses_overwrite_without_force(): void
    {
        $command = $this->makeCommand(['order']);
        $tester = new CommandTester($command);

        // First write — creates file
        $tester->execute(
            ['--type' => 'order', '--count' => '5', '--name' => 'DupeSeeder'],
            ['interactive' => false],
        );

        // Second write — should refuse
        $exit = $tester->execute(
            ['--type' => 'order', '--count' => '10', '--name' => 'DupeSeeder'],
            ['interactive' => false],
        );

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('--force', $tester->getDisplay());
    }

    public function test_non_interactive_overwrite_with_force(): void
    {
        $command = $this->makeCommand(['order']);
        $tester = new CommandTester($command);

        $tester->execute(
            ['--type' => 'order', '--count' => '5', '--name' => 'OverwriteMe'],
            ['interactive' => false],
        );
        $exit = $tester->execute(
            ['--type' => 'order', '--count' => '42', '--name' => 'OverwriteMe', '--force' => true],
            ['interactive' => false],
        );

        $this->assertSame(Command::SUCCESS, $exit);
        $config = require $this->workspace . '/dev/seeders/OverwriteMe.php';
        $this->assertSame(42, $config['count']);
    }

    /** @param string[] $knownTypes */
    private function makeCommand(array $knownTypes): SeedMakeCommand
    {
        $generators = array_fill_keys(
            $knownTypes,
            $this->createStub(\RunAsRoot\Seeder\Api\DataGeneratorInterface::class),
        );
        $pool = new DataGeneratorPool($generators);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->workspace);

        return new SeedMakeCommand(new SeederFileBuilder(), $pool, $directoryList);
    }

    private function removeRecursive(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
