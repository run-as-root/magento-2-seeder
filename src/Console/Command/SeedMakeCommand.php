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

        $knownTypes = implode(', ', array_keys($this->generatorPool->getAll()));
        $typeDesc = $knownTypes !== ''
            ? sprintf('Entity type (one of: %s)', $knownTypes)
            : 'Entity type (e.g. order, customer)';

        $this->addOption('type', null, InputOption::VALUE_REQUIRED, $typeDesc);
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'Number of entities to generate');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'File format: php|json|yaml (default: php)');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'File name (default: {Type}Seeder)');
        $this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Faker locale (default: en_US)');
        $this->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Faker seed (omit for random)');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite file without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isInteractive = $input->isInteractive();

        $rawType = $input->getOption('type');
        $rawCount = $input->getOption('count');
        $rawName = $input->getOption('name');
        $rawFormat = $input->getOption('format');
        $rawLocale = $input->getOption('locale');
        $rawSeed = $input->getOption('seed');

        if (!$isInteractive && ($rawType === null || $rawType === '' || $rawCount === null || $rawCount === '')) {
            $output->writeln(
                '<error>Non-interactive mode requires --type and --count (or run in a TTY).</error>',
            );
            return Command::FAILURE;
        }

        // 1. Resolve entity types (multi-select in interactive mode; single from flag otherwise).
        /** @var string[] $types */
        $types = [];
        if ($rawType !== null && $rawType !== '') {
            $types = [(string) $rawType];
        } elseif ($isInteractive) {
            $types = $this->promptForTypes();
        }

        if ($types === []) {
            $output->writeln('<error>At least one entity type is required.</error>');
            return Command::FAILURE;
        }

        foreach ($types as $t) {
            if (!$this->generatorPool->has($t)) {
                $available = implode(', ', array_keys($this->generatorPool->getAll()));
                $output->writeln(sprintf(
                    '<error>Unknown type "%s". Available: %s</error>',
                    $t,
                    $available,
                ));
                return Command::FAILURE;
            }
        }

        // 2. Resolve per-type counts.
        /** @var array<string, int> $counts */
        $counts = [];
        if ($rawCount !== null && $rawCount !== '') {
            // Flag-driven path stays single-type (validated above: $types is [single]).
            $counts[$types[0]] = (int) $rawCount;
        } else {
            foreach ($types as $t) {
                $counts[$t] = (int) \Laravel\Prompts\text(
                    label: sprintf('How many %s?', $t),
                    default: '10',
                    validate: fn (string $v) => (ctype_digit($v) && (int) $v > 0)
                        ? null
                        : 'Count must be a positive integer',
                );
            }
        }

        foreach ($counts as $t => $c) {
            if ($c < 1) {
                $output->writeln('<error>Count must be a positive integer.</error>');
                return Command::FAILURE;
            }
        }

        // 3. Resolve filename (only when ONE type selected; multi-type auto-generates).
        $singleType = count($types) === 1;
        $name = null;
        if ($singleType) {
            $firstType = $types[0];
            $name = (string) ($rawName !== null && $rawName !== '' ? $rawName : $this->defaultName($firstType));
            if ($isInteractive && ($rawName === null || $rawName === '')) {
                $name = \Laravel\Prompts\text(
                    label: 'File name',
                    default: $this->defaultName($firstType),
                    validate: fn (string $v) => str_ends_with($v, 'Seeder') ? null : 'Name must end in "Seeder"',
                );
            }

            if (!str_ends_with($name, 'Seeder')) {
                $output->writeln(sprintf(
                    "<error>Name must end in 'Seeder' (got '%s')</error>",
                    $name,
                ));
                return Command::FAILURE;
            }
        }

        // 4. Shared locale / seed / format prompts.
        $locale = (string) ($rawLocale !== null && $rawLocale !== '' ? $rawLocale : '');
        if ($isInteractive && ($rawLocale === null || $rawLocale === '')) {
            $locale = (string) \Laravel\Prompts\search(
                label: 'Faker locale',
                options: fn (string $q) => $this->filterLocales($q),
            );
        }
        if ($locale === '') {
            $locale = 'en_US';
        }

        $seed = $rawSeed !== null && $rawSeed !== '' ? (int) $rawSeed : null;
        if ($isInteractive && $rawSeed === null) {
            $seedInput = \Laravel\Prompts\text(label: 'Faker seed (blank for random)', default: '');
            $seed = $seedInput === '' ? null : (int) $seedInput;
        }

        $format = (string) $rawFormat;
        if ($isInteractive && ($rawFormat === null || $rawFormat === '')) {
            $format = (string) \Laravel\Prompts\select(
                label: 'File format',
                options: ['php' => 'PHP', 'json' => 'JSON', 'yaml' => 'YAML'],
                default: 'php',
            );
        }
        if ($format === '') {
            $format = 'php';
        }

        if (!in_array($format, SeederFileBuilder::SUPPORTED_FORMATS, true)) {
            $output->writeln(sprintf(
                '<error>Unknown format "%s". Use: %s</error>',
                $format,
                implode(', ', SeederFileBuilder::SUPPORTED_FORMATS),
            ));
            return Command::FAILURE;
        }

        // 5. Ensure seeders directory exists.
        $seedersDir = rtrim($this->directoryList->getRoot(), '/') . '/' . self::SEEDERS_DIR;
        if (!is_dir($seedersDir) && !mkdir($seedersDir, 0o755, true) && !is_dir($seedersDir)) {
            $output->writeln(sprintf('<error>Could not create %s</error>', $seedersDir));
            return Command::FAILURE;
        }

        // 6. Write each target file with per-type overwrite confirm.
        $force = (bool) $input->getOption('force');
        $created = [];
        $skipped = [];

        foreach ($types as $t) {
            $fileName = $singleType && $name !== null ? $name : $this->defaultName($t);
            $target = $seedersDir . '/' . $fileName . '.' . $format;

            if (file_exists($target) && !$force) {
                if (!$isInteractive) {
                    $output->writeln(sprintf(
                        '<error>%s already exists. Pass --force to overwrite.</error>',
                        $target,
                    ));
                    return Command::FAILURE;
                }
                $overwrite = \Laravel\Prompts\confirm(
                    label: sprintf('%s already exists. Overwrite?', $target),
                    default: false,
                );
                if (!$overwrite) {
                    $skipped[] = $target;
                    $output->writeln(sprintf('<comment>Skipped %s</comment>', $target));
                    continue;
                }
            }

            file_put_contents(
                $target,
                $this->builder->build($t, $counts[$t], $locale, $seed, $format),
            );
            $created[] = $target;
            $output->writeln(sprintf('<info>Created %s</info>', $target));
        }

        if ($created === [] && $skipped !== []) {
            $output->writeln('<comment>No files written.</comment>');
        }

        return Command::SUCCESS;
    }

    /** @return string[] */
    private function promptForTypes(): array
    {
        $knownTypes = array_keys($this->generatorPool->getAll());
        $options = [];
        foreach ($knownTypes as $t) {
            $deps = $this->generatorPool->get($t)->getDependencies();
            $options[$t] = $deps === []
                ? $t
                : sprintf('%s — cascades: %s', $t, implode(', ', $deps));
        }

        /** @var array<int, string> $selected */
        $selected = \Laravel\Prompts\multiselect(
            label: 'Entity types',
            options: $options,
            required: true,
        );

        return array_values($selected);
    }

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

        $filtered = array_values(array_filter(
            $locales,
            static fn (string $l) => stripos($l, $query) !== false,
        ));
        return array_combine($filtered, $filtered);
    }

    private function defaultName(string $type): string
    {
        $camel = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $type)));
        return $camel . 'Seeder';
    }
}
