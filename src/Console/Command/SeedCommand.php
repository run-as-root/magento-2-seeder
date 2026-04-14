<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Console\Command;

use DavidLambauer\Seeder\Service\SeederRunConfig;
use DavidLambauer\Seeder\Service\SeederRunner;
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code already set
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

    /** @return string[] */
    private function parseCommaSeparated(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }
}
