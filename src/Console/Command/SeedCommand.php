<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Console\Command;

use RunAsRoot\Seeder\Service\GenerateRunConfig;
use RunAsRoot\Seeder\Service\GenerateRunner;
use RunAsRoot\Seeder\Service\SeederRunConfig;
use RunAsRoot\Seeder\Service\SeederRunner;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly SeederRunner $runner,
        private readonly GenerateRunner $generateRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed');
        $this->setDescription('Seed the database with test data');

        $this->addOption(
            'only',
            null,
            InputOption::VALUE_REQUIRED,
            'Only run specific seeder types (comma-separated, e.g. customer,order)'
        );
        $this->addOption(
            'exclude',
            null,
            InputOption::VALUE_REQUIRED,
            'Exclude specific seeder types (comma-separated, e.g. cms)'
        );
        $this->addOption(
            'fresh',
            null,
            InputOption::VALUE_NONE,
            'Clean existing entity data before seeding'
        );
        $this->addOption(
            'stop-on-error',
            null,
            InputOption::VALUE_NONE,
            'Stop execution on first seeder error'
        );
        $this->addOption(
            'generate',
            null,
            InputOption::VALUE_REQUIRED,
            'Generate fake data (e.g. order:1000,customer:500)'
        );
        $this->addOption(
            'locale',
            null,
            InputOption::VALUE_REQUIRED,
            'Faker locale (default: en_US)',
            'en_US'
        );
        $this->addOption(
            'seed',
            null,
            InputOption::VALUE_REQUIRED,
            'Faker seed for deterministic generation'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code already set
        }

        $generateOption = $input->getOption('generate');
        if ($generateOption !== null && $generateOption !== '') {
            return $this->executeGenerate($input, $output);
        }

        $config = new SeederRunConfig(
            only: $this->parseCommaSeparated($input->getOption('only')),
            exclude: $this->parseCommaSeparated($input->getOption('exclude')),
            fresh: (bool) $input->getOption('fresh'),
            stopOnError: (bool) $input->getOption('stop-on-error'),
        );

        if ($config->fresh) {
            $output->writeln('<comment>Fresh mode: cleaning existing data...</comment>');
        }

        $results = $this->runner->run($config);

        if ($results === []) {
            $output->writeln('<comment>No seeders found in dev/seeders/</comment>');

            return Command::SUCCESS;
        }

        $hasError = false;
        foreach ($results as $result) {
            if ($result['success']) {
                $output->writeln(sprintf('<info>Seeding %s... done</info>', $result['type']));
            } else {
                $hasError = true;
                $output->writeln(sprintf(
                    '<error>Seeding %s... failed: %s</error>',
                    $result['type'],
                    $result['error'] ?? 'Unknown error'
                ));
            }
        }

        $successCount = count(array_filter($results, static fn (array $r): bool => $r['success']));
        $output->writeln('');
        $output->writeln(sprintf('Done. %d seeder(s) completed.', $successCount));

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }

    private function executeGenerate(InputInterface $input, OutputInterface $output): int
    {
        $counts = $this->parseGenerateCounts($input->getOption('generate'));
        $config = new GenerateRunConfig(
            counts: $counts,
            locale: $input->getOption('locale') ?? 'en_US',
            seed: $input->getOption('seed') !== null ? (int) $input->getOption('seed') : null,
            fresh: (bool) $input->getOption('fresh'),
            stopOnError: (bool) $input->getOption('stop-on-error'),
        );

        if ($config->fresh) {
            $output->writeln('<comment>Fresh mode: cleaning existing data...</comment>');
        }

        $output->writeln(sprintf('<comment>Generating with locale: %s</comment>', $config->locale));

        $results = $this->generateRunner->run($config);

        $hasError = false;
        foreach ($results as $result) {
            if ($result['success']) {
                $output->writeln(sprintf('<info>Generated %d %s(s)... done</info>', $result['count'], $result['type']));
                continue;
            }

            $hasError = true;
            $output->writeln(sprintf(
                '<error>Generated %d/%d %s(s), %d failed: %s</error>',
                $result['count'],
                $result['count'] + ($result['failed'] ?? 0),
                $result['type'],
                $result['failed'] ?? 0,
                $result['error'] ?? 'Unknown error'
            ));
        }

        $totalCount = array_sum(array_column($results, 'count'));
        $totalFailed = array_sum(array_map(static fn (array $r): int => $r['failed'] ?? 0, $results));
        $output->writeln('');
        if ($hasError) {
            $output->writeln(sprintf(
                '<error>Done with errors. %d entities generated, %d failed. See var/log for details.</error>',
                $totalCount,
                $totalFailed
            ));
        } else {
            $output->writeln(sprintf('Done. %d entities generated.', $totalCount));
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return array<string, int> */
    private function parseGenerateCounts(string $value): array
    {
        $counts = [];
        foreach (explode(',', $value) as $pair) {
            $parts = explode(':', trim($pair));
            if (count($parts) === 2) {
                $counts[trim($parts[0])] = (int) trim($parts[1]);
            }
        }

        return $counts;
    }

    /** @return string[] */
    private function parseCommaSeparated(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }
}
