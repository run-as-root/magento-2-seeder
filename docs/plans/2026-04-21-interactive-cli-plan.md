# Interactive CLI & `db:seed:make` Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Eliminate the empty-`dev/seeders/` dead-end UX by adding a `db:seed:make` scaffolder command, and uniformly upgrade output across all three `db:seed*` commands using `laravel/prompts`.

**Architecture:** One new command (`db:seed:make`) handles interactive and flag-driven seeder-file scaffolding via a pure `SeederFileBuilder` service that emits PHP / JSON / YAML shapes already supported by `ArraySeederAdapter`. Existing `db:seed` and `db:seed:status` get an output-only Laravel Prompts rewrite plus a hint on empty `dev/seeders/`. A thin `ProgressReporter` adapts `GenerateRunner`'s push-style callback onto Laravel Prompts' `Progress`.

**Tech Stack:** PHP 8.1+, Magento 2 module, Symfony Console 6.2+/7, PHPUnit 10, `laravel/prompts` (new), `symfony/yaml` (already installed), `fakerphp/faker` (already installed).

**Design doc:** `docs/plans/2026-04-21-interactive-cli-design.md`

**Conventions to follow:**
- Test methods are `snake_case` and classes are `final` (from `~/.claude/CLAUDE.md`).
- Namespace root is `RunAsRoot\Seeder\`.
- Company-module convention, not personal.
- PSR-12 + Magento coding standard (enforced by `composer phpcs`).
- Never skip hooks; run `composer check` before each commit.

**Commit convention (from recent `git log`):** `type(scope): summary` — e.g. `feat(seed-make): ...`, `test(seed-make): ...`, `refactor(seed-command): ...`, `docs(readme): ...`.

---

## Task 1: Add `laravel/prompts` dependency

**Files:**
- Modify: `composer.json`

**Step 1: Add to `require`**

Edit `composer.json`. Add this line to the `require` block (alphabetical order — goes right after `fakerphp/faker`):

```json
"laravel/prompts": "^0.3",
```

**Step 2: Install**

Run: `composer update laravel/prompts --no-interaction -W`
Expected: `laravel/prompts` and any transitive dependencies (`symfony/console` may bump to ^6.2) are installed. No errors.

**Step 3: Verify no regressions**

Run: `composer check`
Expected: phpcs, phpstan, phpunit all pass.

**Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat(deps): add laravel/prompts for interactive CLI"
```

---

## Task 2: `SeederFileBuilder` — PHP format

**Files:**
- Create: `src/Service/Scaffold/SeederFileBuilder.php`
- Create: `Test/Unit/Service/Scaffold/SeederFileBuilderTest.php`

**Step 1: Write the failing test**

Create `Test/Unit/Service/Scaffold/SeederFileBuilderTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service\Scaffold;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Service\Scaffold\SeederFileBuilder;

final class SeederFileBuilderTest extends TestCase
{
    public function test_builds_php_seeder_without_seed(): void
    {
        $builder = new SeederFileBuilder();

        $content = $builder->build(
            type: 'order',
            count: 100,
            locale: 'en_US',
            seed: null,
            format: 'php',
        );

        $this->assertStringContainsString("declare(strict_types=1);", $content);
        $this->assertStringContainsString("'type' => 'order'", $content);
        $this->assertStringContainsString("'count' => 100", $content);
        $this->assertStringContainsString("'locale' => 'en_US'", $content);
        $this->assertStringNotContainsString("'seed' =>", $content);
        // Commented seed hint still present so users know the key is available.
        $this->assertStringContainsString("// 'seed'", $content);
    }

    public function test_php_output_is_a_returnable_array(): void
    {
        $builder = new SeederFileBuilder();
        $content = $builder->build('order', 5, 'en_US', null, 'php');

        $path = tempnam(sys_get_temp_dir(), 'seeder-');
        file_put_contents($path, $content);

        try {
            $result = require $path;
            $this->assertSame(['type' => 'order', 'count' => 5, 'locale' => 'en_US'], $result);
        } finally {
            unlink($path);
        }
    }
}
```

**Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: FAIL — `Class "RunAsRoot\Seeder\Service\Scaffold\SeederFileBuilder" not found`.

**Step 3: Implement the minimal code**

Create `src/Service/Scaffold/SeederFileBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Scaffold;

use InvalidArgumentException;

class SeederFileBuilder
{
    public const FORMAT_PHP = 'php';
    public const FORMAT_JSON = 'json';
    public const FORMAT_YAML = 'yaml';

    public const SUPPORTED_FORMATS = [self::FORMAT_PHP, self::FORMAT_JSON, self::FORMAT_YAML];

    public function build(
        string $type,
        int $count,
        string $locale,
        ?int $seed,
        string $format,
    ): string {
        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported format "%s". Use one of: %s',
                $format,
                implode(', ', self::SUPPORTED_FORMATS),
            ));
        }

        return match ($format) {
            self::FORMAT_PHP => $this->buildPhp($type, $count, $locale, $seed),
        };
    }

    private function buildPhp(string $type, int $count, string $locale, ?int $seed): string
    {
        $seedLine = $seed !== null
            ? sprintf("    'seed' => %d,\n", $seed)
            : sprintf("    // 'seed' => 42,\n");

        return <<<PHP
            <?php

            declare(strict_types=1);

            return [
                'type' => '{$type}',
                'count' => {$count},
                'locale' => '{$locale}',
            {$seedLine}];

            PHP;
    }
}
```

Note: the heredoc indentation strips leading whitespace via PHP 7.3+ flexible heredoc — the closing `PHP;` column determines the strip column. Be careful to align.

**Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: PASS (both tests).

**Step 5: Run static analysis and linting**

Run: `composer phpcs -- src/Service/Scaffold Test/Unit/Service/Scaffold`
Run: `composer phpstan`
Expected: no errors.

**Step 6: Commit**

```bash
git add src/Service/Scaffold/SeederFileBuilder.php Test/Unit/Service/Scaffold/SeederFileBuilderTest.php
git commit -m "feat(scaffold): add SeederFileBuilder with PHP format"
```

---

## Task 3: `SeederFileBuilder` — JSON format

**Files:**
- Modify: `src/Service/Scaffold/SeederFileBuilder.php`
- Modify: `Test/Unit/Service/Scaffold/SeederFileBuilderTest.php`

**Step 1: Add failing test**

Append to `SeederFileBuilderTest`:

```php
public function test_builds_json_seeder_without_seed(): void
{
    $builder = new SeederFileBuilder();

    $content = $builder->build('order', 100, 'en_US', null, 'json');

    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(
        ['type' => 'order', 'count' => 100, 'locale' => 'en_US'],
        $decoded,
    );
    $this->assertArrayNotHasKey('seed', $decoded);
}

public function test_builds_json_seeder_with_seed(): void
{
    $builder = new SeederFileBuilder();
    $content = $builder->build('customer', 50, 'de_DE', 42, 'json');

    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(
        ['type' => 'customer', 'count' => 50, 'locale' => 'de_DE', 'seed' => 42],
        $decoded,
    );
}
```

**Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: FAIL — match expression doesn't handle 'json'.

**Step 3: Implement JSON branch**

Add to `SeederFileBuilder`:

```php
return match ($format) {
    self::FORMAT_PHP  => $this->buildPhp($type, $count, $locale, $seed),
    self::FORMAT_JSON => $this->buildJson($type, $count, $locale, $seed),
};
```

```php
private function buildJson(string $type, int $count, string $locale, ?int $seed): string
{
    $payload = ['type' => $type, 'count' => $count, 'locale' => $locale];
    if ($seed !== null) {
        $payload['seed'] = $seed;
    }

    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
```

**Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: PASS (all JSON + PHP tests).

**Step 5: Commit**

```bash
git add src/Service/Scaffold/SeederFileBuilder.php Test/Unit/Service/Scaffold/SeederFileBuilderTest.php
git commit -m "feat(scaffold): add JSON format to SeederFileBuilder"
```

---

## Task 4: `SeederFileBuilder` — YAML format

**Files:**
- Modify: `src/Service/Scaffold/SeederFileBuilder.php`
- Modify: `Test/Unit/Service/Scaffold/SeederFileBuilderTest.php`

**Step 1: Add failing test**

Append:

```php
public function test_builds_yaml_seeder_without_seed(): void
{
    $builder = new SeederFileBuilder();
    $content = $builder->build('order', 100, 'en_US', null, 'yaml');

    $parsed = \Symfony\Component\Yaml\Yaml::parse($content);
    $this->assertSame(
        ['type' => 'order', 'count' => 100, 'locale' => 'en_US'],
        $parsed,
    );
}

public function test_builds_yaml_seeder_with_seed(): void
{
    $builder = new SeederFileBuilder();
    $content = $builder->build('product', 25, 'fr_FR', 7, 'yaml');

    $parsed = \Symfony\Component\Yaml\Yaml::parse($content);
    $this->assertSame(
        ['type' => 'product', 'count' => 25, 'locale' => 'fr_FR', 'seed' => 7],
        $parsed,
    );
}
```

**Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: FAIL — 'yaml' not handled.

**Step 3: Implement YAML branch**

Add to `SeederFileBuilder`:

```php
return match ($format) {
    self::FORMAT_PHP  => $this->buildPhp($type, $count, $locale, $seed),
    self::FORMAT_JSON => $this->buildJson($type, $count, $locale, $seed),
    self::FORMAT_YAML => $this->buildYaml($type, $count, $locale, $seed),
};
```

```php
private function buildYaml(string $type, int $count, string $locale, ?int $seed): string
{
    $payload = ['type' => $type, 'count' => $count, 'locale' => $locale];
    if ($seed !== null) {
        $payload['seed'] = $seed;
    }

    return \Symfony\Component\Yaml\Yaml::dump($payload);
}
```

**Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: PASS (all tests).

**Step 5: Add invalid-format test**

```php
public function test_rejects_unsupported_format(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Unsupported format/');

    (new SeederFileBuilder())->build('order', 1, 'en_US', null, 'toml');
}
```

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Service/Scaffold/SeederFileBuilder.php Test/Unit/Service/Scaffold/SeederFileBuilderTest.php
git commit -m "feat(scaffold): add YAML format + unsupported-format guard"
```

---

## Task 5: `SeederFileBuilder` roundtrip via `ArraySeederAdapter`

The whole point of the builder is to emit shapes the existing `ArraySeederAdapter` can consume. Prove it with a parity test.

**Files:**
- Modify: `Test/Unit/Service/Scaffold/SeederFileBuilderTest.php`

**Step 1: Add failing roundtrip test**

```php
public function test_php_output_is_consumable_by_array_seeder_adapter(): void
{
    $builder = new SeederFileBuilder();
    $content = $builder->build('order', 3, 'en_US', 99, 'php');

    $path = tempnam(sys_get_temp_dir(), 'seeder-');
    file_put_contents($path, $content);

    try {
        $config = require $path;
        $this->assertIsArray($config);
        $this->assertSame('order', $config['type']);
        $this->assertSame(3, $config['count']);
        $this->assertSame(99, $config['seed']);
    } finally {
        unlink($path);
    }
}
```

(We don't instantiate `ArraySeederAdapter` here — it takes `EntityHandlerPool` and `GenerateRunner` which are Magento objects. The shape check is sufficient at unit-test layer; full roundtrip lives in the integration test later.)

**Step 2: Run**

Run: `vendor/bin/phpunit --filter SeederFileBuilderTest`
Expected: PASS.

**Step 3: Commit**

```bash
git add Test/Unit/Service/Scaffold/SeederFileBuilderTest.php
git commit -m "test(scaffold): assert PHP builder output roundtrips as array config"
```

---

## Task 6: `ProgressReporter` adapter

Adapts `GenerateRunner`'s push-style `onProgress(string $type, int $done, int $total)` callback onto Laravel Prompts' `Progress` class.

**Files:**
- Create: `src/Service/ProgressReporter.php`
- Create: `Test/Unit/Service/ProgressReporterTest.php`

**Step 1: Read Laravel Prompts Progress API via Context7 (optional but recommended)**

If unsure about Progress class signatures:
Run: `# ask Context7 for docs` — use `mcp__context7__resolve-library-id` with `laravel/prompts` then `mcp__context7__query-docs` for `Progress class`. Confirm: `new Progress(string $label, int $steps)`, `->start()`, `->advance()`, `->finish()`.

**Step 2: Write failing test**

Create `Test/Unit/Service/ProgressReporterTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use Laravel\Prompts\Prompt;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Service\ProgressReporter;

final class ProgressReporterTest extends TestCase
{
    protected function setUp(): void
    {
        Prompt::fake();
    }

    public function test_starts_a_progress_on_first_push_per_type(): void
    {
        $reporter = new ProgressReporter();

        $reporter->report('order', 0, 10);
        $reporter->report('order', 1, 10);
        $reporter->report('order', 10, 10);

        // No exception => Progress lifecycle completed cleanly.
        $this->assertTrue(true);
    }

    public function test_finishes_previous_progress_when_type_changes(): void
    {
        $reporter = new ProgressReporter();

        $reporter->report('order', 5, 10);
        $reporter->report('customer', 0, 20);
        $reporter->report('customer', 20, 20);

        $this->assertTrue(true);
    }

    public function test_finish_is_safe_without_active_progress(): void
    {
        $reporter = new ProgressReporter();
        $reporter->finish();

        $this->assertTrue(true);
    }
}
```

Note: Prompts doesn't expose an easy assertion for Progress state in `fake()` mode — the test asserts the lifecycle doesn't throw, which is the main failure mode of a mis-wired adapter. We rely on the integration test for the end-to-end visual.

**Step 3: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ProgressReporterTest`
Expected: FAIL — class not found.

**Step 4: Implement**

Create `src/Service/ProgressReporter.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Laravel\Prompts\Progress;

class ProgressReporter
{
    private ?Progress $progress = null;
    private ?string $currentType = null;

    /**
     * Push-style callback compatible with GenerateRunner::$onProgress signature.
     */
    public function report(string $type, int $done, int $total): void
    {
        if ($total < 1) {
            return;
        }

        if ($this->currentType !== $type) {
            $this->finish();
            $this->currentType = $type;
            $this->progress = new Progress(sprintf('Generating %s', $type), $total);
            $this->progress->start();
        }

        $this->progress?->advance(max(0, $done - ($this->progress->progress ?? 0)));

        if ($done >= $total) {
            $this->finish();
        }
    }

    public function finish(): void
    {
        $this->progress?->finish();
        $this->progress = null;
        $this->currentType = null;
    }

    /**
     * Returns a closure matching GenerateRunner's onProgress callable signature.
     */
    public function asCallable(): callable
    {
        return function (string $type, int $done, int $total): void {
            $this->report($type, $done, $total);
        };
    }
}
```

Note: if `Progress::progress` isn't a public readable property in your Prompts version, track the delta locally (`$this->lastDone = 0; ... $this->progress->advance($done - $this->lastDone); $this->lastDone = $done;` then reset on type change). Verify via Context7 and adjust.

**Step 5: Run tests**

Run: `vendor/bin/phpunit --filter ProgressReporterTest`
Expected: PASS.

Run: `composer phpstan`
Expected: no errors (adjust if phpstan complains about `Progress::progress` access — use the delta-tracking variant above instead).

**Step 6: Commit**

```bash
git add src/Service/ProgressReporter.php Test/Unit/Service/ProgressReporterTest.php
git commit -m "feat(progress): add ProgressReporter adapter for Laravel Prompts"
```

---

## Task 7: `SeedMakeCommand` — flag-driven happy path

Start with the non-interactive branch; it's the simpler code path and gives us a working `--type=X --count=Y` skeleton to layer prompts onto.

**Files:**
- Create: `src/Console/Command/SeedMakeCommand.php`
- Create: `Test/Unit/Console/Command/SeedMakeCommandTest.php`

**Step 1: Failing test**

Create `Test/Unit/Console/Command/SeedMakeCommandTest.php`:

```php
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

    /** @param string[] $knownTypes */
    private function makeCommand(array $knownTypes): SeedMakeCommand
    {
        $generators = array_fill_keys($knownTypes, $this->createStub(\RunAsRoot\Seeder\Api\DataGeneratorInterface::class));
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
```

**Step 2: Run — fails (class doesn't exist)**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: FAIL — `Class "RunAsRoot\Seeder\Console\Command\SeedMakeCommand" not found`.

**Step 3: Implement minimal command**

Create `src/Console/Command/SeedMakeCommand.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\Scaffold\SeederFileBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedMakeCommand extends Command
{
    private const SEEDERS_DIR = 'dev/seeders';

    public function __construct(
        private readonly SeederFileBuilder $builder,
        private readonly DataGeneratorPool $generatorPool,
        private readonly DirectoryList $directoryList,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed:make');
        $this->setDescription('Scaffold a new seeder file in dev/seeders/');

        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Entity type (e.g. order, customer)');
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'Number of entities to generate');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'File format: php|json|yaml', 'php');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'File name (default: {Type}Seeder)');
        $this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Faker locale', 'en_US');
        $this->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Faker seed (omit for random)');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite file without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = (string) $input->getOption('type');
        $count = (int) $input->getOption('count');
        $format = (string) $input->getOption('format');
        $locale = (string) ($input->getOption('locale') ?: 'en_US');
        $seedOption = $input->getOption('seed');
        $seed = $seedOption !== null && $seedOption !== '' ? (int) $seedOption : null;
        $name = (string) ($input->getOption('name') ?: $this->defaultName($type));

        $seedersDir = rtrim($this->directoryList->getRoot(), '/') . '/' . self::SEEDERS_DIR;
        if (!is_dir($seedersDir) && !mkdir($seedersDir, 0o755, true) && !is_dir($seedersDir)) {
            $output->writeln(sprintf('<error>Could not create %s</error>', $seedersDir));
            return Command::FAILURE;
        }

        $target = $seedersDir . '/' . $name . '.' . $format;

        file_put_contents($target, $this->builder->build($type, $count, $locale, $seed, $format));

        $output->writeln(sprintf('<info>Created %s</info>', $target));

        return Command::SUCCESS;
    }

    private function defaultName(string $type): string
    {
        $camel = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $type)));
        return $camel . 'Seeder';
    }
}
```

**Step 4: Run the test**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Console/Command/SeedMakeCommand.php Test/Unit/Console/Command/SeedMakeCommandTest.php
git commit -m "feat(seed-make): add command skeleton with flag-driven scaffold"
```

---

## Task 8: Validation — invalid type / count / format

**Files:**
- Modify: `src/Console/Command/SeedMakeCommand.php`
- Modify: `Test/Unit/Console/Command/SeedMakeCommandTest.php`

**Step 1: Failing tests**

Append to `SeedMakeCommandTest`:

```php
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
```

**Step 2: Run — fails**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: three failures.

**Step 3: Add validation**

In `SeedMakeCommand::execute`, add checks before the file write:

```php
if (!$this->generatorPool->has($type)) {
    $available = implode(', ', array_keys($this->generatorPool->getAll()));
    $output->writeln(sprintf(
        '<error>Unknown type "%s". Available: %s</error>',
        $type,
        $available,
    ));
    return Command::FAILURE;
}

if ($count < 1) {
    $output->writeln('<error>Count must be a positive integer.</error>');
    return Command::FAILURE;
}

if (!in_array($format, SeederFileBuilder::SUPPORTED_FORMATS, true)) {
    $output->writeln(sprintf(
        '<error>Unknown format "%s". Use: %s</error>',
        $format,
        implode(', ', SeederFileBuilder::SUPPORTED_FORMATS),
    ));
    return Command::FAILURE;
}
```

**Step 4: Run tests**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: PASS (all six tests so far).

**Step 5: Commit**

```bash
git add src/Console/Command/SeedMakeCommand.php Test/Unit/Console/Command/SeedMakeCommandTest.php
git commit -m "feat(seed-make): validate type/count/format flags"
```

---

## Task 9: Non-TTY missing required flags

**Files:**
- Modify: `src/Console/Command/SeedMakeCommand.php`
- Modify: `Test/Unit/Console/Command/SeedMakeCommandTest.php`

**Step 1: Failing test**

```php
public function test_non_interactive_without_required_flags_errors_out(): void
{
    $command = $this->makeCommand(['order']);
    $tester = new CommandTester($command);

    $exit = $tester->execute([], ['interactive' => false]);

    $this->assertSame(Command::FAILURE, $exit);
    $this->assertMatchesRegularExpression('/--type.*--count/s', $tester->getDisplay());
}
```

**Step 2: Run — fails**

Expected: currently passes blank `type`/`count` through validation, which catches it — but message won't mention `--type --count` together. Tweak the error.

**Step 3: Add up-front guard**

At the top of `execute`:

```php
$isInteractive = $input->isInteractive();

$rawType = $input->getOption('type');
$rawCount = $input->getOption('count');

if (!$isInteractive && ($rawType === null || $rawType === '' || $rawCount === null || $rawCount === '')) {
    $output->writeln(
        '<error>Non-interactive mode requires --type and --count (or run in a TTY).</error>',
    );
    return Command::FAILURE;
}
```

**Step 4: Run**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Console/Command/SeedMakeCommand.php Test/Unit/Console/Command/SeedMakeCommandTest.php
git commit -m "feat(seed-make): guard non-interactive without required flags"
```

---

## Task 10: Overwrite handling with `--force`

**Files:**
- Modify: `src/Console/Command/SeedMakeCommand.php`
- Modify: `Test/Unit/Console/Command/SeedMakeCommandTest.php`

**Step 1: Failing tests**

```php
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
```

**Step 2: Run — fails**

**Step 3: Implement**

Before `file_put_contents`:

```php
$force = (bool) $input->getOption('force');

if (file_exists($target) && !$force) {
    if (!$isInteractive) {
        $output->writeln(sprintf(
            '<error>%s already exists. Pass --force to overwrite.</error>',
            $target,
        ));
        return Command::FAILURE;
    }
    // Interactive overwrite confirm is added in Task 12.
}
```

**Step 4: Run**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Console/Command/SeedMakeCommand.php Test/Unit/Console/Command/SeedMakeCommandTest.php
git commit -m "feat(seed-make): require --force to overwrite existing file"
```

---

## Task 11: Interactive prompts

**Files:**
- Modify: `src/Console/Command/SeedMakeCommand.php`
- Modify: `Test/Unit/Console/Command/SeedMakeCommandTest.php`

**Step 1: Failing test using `Prompt::fake()`**

Add to the top of the test file:

```php
use Laravel\Prompts\Prompt;
```

Add to `setUp`:

```php
Prompt::fake();
```

Add test:

```php
public function test_interactive_prompts_collect_all_fields(): void
{
    $command = $this->makeCommand(['order', 'customer', 'product']);

    Prompt::fake([
        // select: Entity type
        'order',
        // text: File name (accept default -> enter)
        'OrderSeeder',
        // text: How many?
        '12',
        // search: Faker locale -> select en_US
        'en_US',
        // text: Faker seed (blank)
        '',
        // select: File format
        'php',
    ]);

    $tester = new CommandTester($command);
    $exit = $tester->execute([], ['interactive' => true]);

    $this->assertSame(Command::SUCCESS, $exit);

    $file = $this->workspace . '/dev/seeders/OrderSeeder.php';
    $this->assertFileExists($file);

    $config = require $file;
    $this->assertSame('order', $config['type']);
    $this->assertSame(12, $config['count']);
}
```

Note: the `Prompt::fake()` API accepts an array of scripted responses in order. Verify exact signature via Context7 — in recent versions it's `Prompt::fake(['answer1', 'answer2'])`. If the API differs (e.g. requires closures), adapt. If overwrite prompt is reached, prepend a `'yes'`/`'no'` answer.

**Step 2: Run — fails (no interactive branch yet)**

**Step 3: Implement interactive branch**

In `execute()`, replace the non-interactive-only logic with:

```php
if ($isInteractive && ($rawType === null || $rawType === '')) {
    $type = \Laravel\Prompts\select(
        label: 'Entity type',
        options: array_combine(
            array_keys($this->generatorPool->getAll()),
            array_keys($this->generatorPool->getAll()),
        ),
    );
}

if ($isInteractive && ($input->getOption('name') === null || $input->getOption('name') === '')) {
    $name = \Laravel\Prompts\text(
        label: 'File name',
        default: $this->defaultName($type),
        validate: fn (string $v) => str_ends_with($v, 'Seeder') ? null : 'Name must end in "Seeder"',
    );
}

if ($isInteractive && ($rawCount === null || $rawCount === '')) {
    $count = (int) \Laravel\Prompts\text(
        label: 'How many?',
        default: '10',
        validate: fn (string $v) => (ctype_digit($v) && (int) $v > 0) ? null : 'Count must be a positive integer',
    );
}

if ($isInteractive && ($input->getOption('locale') === null || $input->getOption('locale') === 'en_US')) {
    $locale = \Laravel\Prompts\search(
        label: 'Faker locale',
        options: fn (string $q) => $this->filterLocales($q),
    );
}

if ($isInteractive && $input->getOption('seed') === null) {
    $seedInput = \Laravel\Prompts\text(label: 'Faker seed (blank for random)', default: '');
    $seed = $seedInput === '' ? null : (int) $seedInput;
}

if ($isInteractive && ($input->getOption('format') === null || $input->getOption('format') === 'php')) {
    $format = \Laravel\Prompts\select(
        label: 'File format',
        options: ['php' => 'PHP', 'json' => 'JSON', 'yaml' => 'YAML'],
        default: 'php',
    );
}

// Interactive overwrite confirm (only reached when file exists AND interactive AND no --force)
if ($isInteractive && file_exists($target) && !$force) {
    $keep = \Laravel\Prompts\confirm(
        label: sprintf('%s already exists. Overwrite?', $target),
        default: false,
    );
    if (!$keep) {
        $output->writeln('<comment>Aborted.</comment>');
        return Command::SUCCESS;
    }
}
```

Add a locale filter helper:

```php
/** @return array<string, string> */
private function filterLocales(string $query): array
{
    $locales = [
        'en_US', 'en_GB', 'en_AU', 'en_CA',
        'de_DE', 'de_AT', 'de_CH',
        'fr_FR', 'fr_CA', 'es_ES', 'es_MX',
        'it_IT', 'nl_NL', 'pt_BR', 'pt_PT',
        'pl_PL', 'sv_SE', 'ja_JP', 'zh_CN',
    ];

    if ($query === '') {
        return array_combine($locales, $locales);
    }

    $filtered = array_values(array_filter($locales, static fn (string $l) => stripos($l, $query) !== false));
    return array_combine($filtered, $filtered);
}
```

Note: the exact option order you prompt in must match the `Prompt::fake()` script in the test. Adjust test or implementation until they align.

**Step 4: Run**

Run: `vendor/bin/phpunit --filter SeedMakeCommandTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Console/Command/SeedMakeCommand.php Test/Unit/Console/Command/SeedMakeCommandTest.php
git commit -m "feat(seed-make): add interactive prompts flow"
```

---

## Task 12: Register `SeedMakeCommand` in di.xml

**Files:**
- Modify: `src/etc/di.xml`

**Step 1: Add registration**

Edit `src/etc/di.xml`. In the `<type name="Magento\Framework\Console\CommandListInterface">` block, add a line inside `<argument name="commands">`:

```xml
<item name="seeder_db_seed_make" xsi:type="object">RunAsRoot\Seeder\Console\Command\SeedMakeCommand</item>
```

**Step 2: Smoke-test in Magento (if Warden env available)**

Per `reference_mage_os_env.md`, copy the module into the Warden env's `vendor/` (symlinks break) and run:

```bash
bin/magento setup:di:compile
bin/magento list | grep db:seed
```

Expected: `db:seed:make` appears in the list alongside `db:seed` and `db:seed:status`.

**Step 3: Commit**

```bash
git add src/etc/di.xml
git commit -m "feat(seed-make): register db:seed:make in CommandListInterface"
```

---

## Task 13: Update `SeedCommand` output + empty-dir hint

**Files:**
- Modify: `src/Console/Command/SeedCommand.php`
- Modify: `Test/Unit/Console/Command/SeedCommandTest.php`

**Step 1: Failing test — empty dir hint**

Append to `SeedCommandTest`:

```php
public function test_empty_dev_seeders_prints_make_hint(): void
{
    $runner = $this->createMock(SeederRunner::class);
    $runner->method('run')->willReturn([]);

    $command = new SeedCommand(
        $this->createMock(State::class),
        $runner,
        $this->createMock(GenerateRunner::class),
        $this->createMock(Registry::class),
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    $this->assertStringContainsString('db:seed:make', $tester->getDisplay());
}
```

**Step 2: Run — fails**

**Step 3: Update command**

Before the existing `No seeders found in dev/seeders/` line, add a `<comment>` line so the message becomes:

```php
$output->writeln('<comment>No seeders found in dev/seeders/</comment>');
$output->writeln('<info>Run bin/magento db:seed:make to scaffold one.</info>');
```

(Keep using `writeln` in `SeedCommand` — Laravel Prompts `warning`/`note` would work too but the command already lives in Symfony-land; stick to writeln for this file to minimize risk. The Prompts upgrade for outputs happens in a follow-up iteration if needed — **KEEP SCOPE TIGHT**. Prompts is used via `ProgressReporter` for generate progress and by `SeedMakeCommand`.)

**Important scope decision:** the design doc called for a full output-layer rewrite of `SeedCommand`/`SeedStatusCommand`, but after reading the existing code, the writeln output is already perfectly functional and a bulk rewrite risks breaking user-visible output that's asserted in existing tests. **Do only the two targeted changes below** (add hint + progress reporter swap). Revisit a broader Prompts aesthetic pass as a follow-up PR.

**Step 4: Swap progress bar**

In `SeedCommand::executeGenerate`, replace the Symfony `ProgressBar` block with a `ProgressReporter`:

```php
$reporter = new \RunAsRoot\Seeder\Service\ProgressReporter();
$onProgress = $reporter->asCallable();

$results = $this->generateRunner->run($config, $onProgress);
$reporter->finish();
```

Remove the old `$progressBar = null; $currentType = null;` closure. The `$output` parameter is no longer needed inside the callback.

Update the constructor to DI-inject `ProgressReporter` (preferred for testability) — add as a readonly ctor arg, update di.xml if Magento DI needs a hint (it shouldn't — concrete class).

**Step 5: Update constructor**

```php
public function __construct(
    private readonly State $appState,
    private readonly SeederRunner $runner,
    private readonly GenerateRunner $generateRunner,
    private readonly Registry $registry,
    private readonly \RunAsRoot\Seeder\Service\ProgressReporter $progressReporter,
) {
```

Then inside `executeGenerate`:

```php
$results = $this->generateRunner->run($config, $this->progressReporter->asCallable());
$this->progressReporter->finish();
```

Update `SeedCommandTest` constructors to pass a real or stubbed `ProgressReporter` — a real instance is fine since it's pure.

**Step 6: Run all tests**

Run: `vendor/bin/phpunit`
Expected: PASS.

**Step 7: Commit**

```bash
git add src/Console/Command/SeedCommand.php Test/Unit/Console/Command/SeedCommandTest.php
git commit -m "feat(seed-command): hint db:seed:make on empty dir, swap progress renderer"
```

---

## Task 14: `SeedStatusCommand` — defer

**Decision:** skip the Prompts `table()` migration in this PR. The existing hand-rolled output is already aligned and no tests need changing. The design's section 4 listed it, but bundling it in this PR adds churn without user-facing gain beyond visual polish.

Create an issue-tracking comment in the design doc follow-ups section by modifying:

**Files:**
- Modify: `docs/plans/2026-04-21-interactive-cli-design.md`

**Step 1: Append a follow-up note**

Add under "Out-of-session follow-ups":
`- Migrate SeedStatusCommand output to Laravel Prompts table().`

**Step 2: Commit**

```bash
git add docs/plans/2026-04-21-interactive-cli-design.md
git commit -m "docs(plans): defer status-command table migration as follow-up"
```

---

## Task 15: Integration test — end-to-end roundtrip

**Files:**
- Create: `Test/Integration/Console/SeedMakeRoundtripTest.php`

**Step 1: Write the integration test**

Follow the pattern from `Test/Integration/NewEntityTypesSmokeTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Integration\Console;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Console\Command\SeedCommand;
use RunAsRoot\Seeder\Console\Command\SeedMakeCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedMakeRoundtripTest extends TestCase
{
    private ObjectManagerInterface $om;

    protected function setUp(): void
    {
        $this->om = Bootstrap::getObjectManager();
    }

    public function test_scaffolded_order_seeder_runs_and_creates_orders(): void
    {
        $make = $this->om->get(SeedMakeCommand::class);
        (new CommandTester($make))->execute(
            [
                '--type' => 'order',
                '--count' => '3',
                '--format' => 'php',
                '--name' => 'SmokeOrderSeeder',
                '--force' => true,
            ],
            ['interactive' => false],
        );

        $seed = $this->om->get(SeedCommand::class);
        $exit = (new CommandTester($seed))->execute(['--only' => 'order']);

        $this->assertSame(0, $exit);

        $searchCriteria = $this->om->get(SearchCriteriaBuilder::class)->create();
        $orders = $this->om->get(OrderRepositoryInterface::class)->getList($searchCriteria)->getItems();

        $this->assertGreaterThanOrEqual(3, count($orders));
    }
}
```

**Step 2: Run in Mage-OS env**

Per project conventions, run against Warden Mage-OS Typesense env:

```bash
vendor/bin/phpunit Test/Integration/Console/SeedMakeRoundtripTest.php
```

Expected: PASS. If the env doesn't have the module installed, copy it per `reference_warden_modules.md` first.

**Step 3: Commit**

```bash
git add Test/Integration/Console/SeedMakeRoundtripTest.php
git commit -m "test(integration): roundtrip db:seed:make scaffolded seeder through db:seed"
```

---

## Task 16: README — document `db:seed:make`

**Files:**
- Modify: `README.md`

**Step 1: Update the "Quick Start" section**

Change:

```markdown
1. Create a `dev/seeders/` directory in your Magento root
2. Drop seeder files in it (copy from `examples/` to get started)
3. Run `bin/magento db:seed`
```

to:

```markdown
1. Scaffold a seeder: `bin/magento db:seed:make`
2. Run `bin/magento db:seed`
```

**Step 2: Add a "Scaffolding" section after "Usage"**

```markdown
## Scaffolding

`db:seed:make` creates a seeder file for you — no need to memorize the format.

```bash
# Interactive
bin/magento db:seed:make

# Flag-driven (CI / scripts)
bin/magento db:seed:make --type=order --count=100 --format=php

# Overwrite an existing file
bin/magento db:seed:make --type=order --count=100 --force
```

Available flags:

| Flag | Default | Notes |
|------|---------|-------|
| `--type` | — | required non-interactive |
| `--count` | — | required non-interactive |
| `--format` | `php` | `php` / `json` / `yaml` |
| `--name` | `{Type}Seeder` | file name without extension |
| `--locale` | `en_US` | Faker locale |
| `--seed` | random | Faker seed for deterministic output |
| `--force` | `false` | overwrite existing file |
```

**Step 3: Commit**

```bash
git add README.md
git commit -m "docs(readme): document db:seed:make scaffolder"
```

---

## Task 17: Final verification

**Files:** none

**Step 1: Full check**

Run: `composer check`
Expected: phpcs, phpstan, phpunit all pass with zero errors.

**Step 2: Smoke-test in Warden Mage-OS env**

Per `reference_warden_modules.md` + `reference_mage_os_env.md`:

```bash
# In mage-os-typesense env
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento db:seed:make  # interactive smoke
bin/magento db:seed
bin/magento db:seed:status
```

Expected: scaffolded file visible in `dev/seeders/`, `db:seed` runs it, status shows non-zero counts.

**Step 3: Review git log**

Run: `git log --oneline main..HEAD`
Expected: ~12 commits, each clearly scoped, TDD-style red→green→refactor per commit.

**Step 4: Hand off**

Per `reference_seeder_release_flow.md`, this module does not auto-publish; user controls the release. Ready for review.

---

## Summary of new files

- `src/Console/Command/SeedMakeCommand.php`
- `src/Service/Scaffold/SeederFileBuilder.php`
- `src/Service/ProgressReporter.php`
- `Test/Unit/Console/Command/SeedMakeCommandTest.php`
- `Test/Unit/Service/Scaffold/SeederFileBuilderTest.php`
- `Test/Unit/Service/ProgressReporterTest.php`
- `Test/Integration/Console/SeedMakeRoundtripTest.php`

## Modified

- `composer.json` / `composer.lock` (add `laravel/prompts`)
- `src/etc/di.xml` (register new command)
- `src/Console/Command/SeedCommand.php` (empty-dir hint + progress reporter)
- `Test/Unit/Console/Command/SeedCommandTest.php` (constructor update + hint test)
- `README.md` (Quick Start + Scaffolding)
- `docs/plans/2026-04-21-interactive-cli-design.md` (follow-up note)
