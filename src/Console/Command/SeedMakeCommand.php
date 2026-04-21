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
