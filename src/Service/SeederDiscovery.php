<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\Seeder\FileParser\JsonParser;
use RunAsRoot\Seeder\Service\Seeder\FileParser\ParserInterface;
use RunAsRoot\Seeder\Service\Seeder\FileParser\PhpArrayParser;
use RunAsRoot\Seeder\Service\Seeder\FileParser\YamlParser;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;

class SeederDiscovery
{
    private const SUPPORTED_GLOBS = [
        '/*Seeder.php',
        '/*Seeder.json',
        '/*Seeder.yaml',
        '/*Seeder.yml',
    ];

    /** @var ParserInterface[] */
    private array $parsers;

    private LoggerInterface $logger;

    /**
     * @param ParserInterface[]|null $parsers
     */
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly ObjectManagerInterface $objectManager,
        private readonly EntityHandlerPool $handlerPool,
        private readonly GenerateRunner $generateRunner,
        ?array $parsers = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->parsers = $parsers ?? [
            new PhpArrayParser(),
            new JsonParser(),
            new YamlParser(),
        ];
        $this->logger = $logger ?? new NullLogger();
    }

    /** @return SeederInterface[] */
    public function discover(): array
    {
        $seedersDir = $this->directoryList->getRoot() . '/dev/seeders';

        if (!is_dir($seedersDir)) {
            return [];
        }

        $files = [];
        foreach (self::SUPPORTED_GLOBS as $pattern) {
            $matched = glob($seedersDir . $pattern);
            if ($matched !== false) {
                $files = array_merge($files, $matched);
            }
        }

        if ($files === []) {
            return [];
        }

        sort($files);

        $seeders = [];
        foreach ($files as $filePath) {
            $seeder = $this->processFile($filePath);
            if ($seeder !== null) {
                $seeders[] = $seeder;
            }
        }

        return $seeders;
    }

    private function processFile(string $filePath): ?SeederInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            return $this->processPhpFile($filePath);
        }

        return $this->processDataFile($filePath);
    }

    private function processPhpFile(string $filePath): ?SeederInterface
    {
        $classesBefore = get_declared_classes();
        $result = include_once $filePath;

        if (is_array($result)) {
            return new ArraySeederAdapter($result, $this->handlerPool, $this->generateRunner);
        }

        // Find any newly declared class that implements SeederInterface
        $newClasses = array_diff(get_declared_classes(), $classesBefore);
        foreach ($newClasses as $className) {
            if (is_a($className, SeederInterface::class, true)) {
                return $this->objectManager->create($className);
            }
        }

        // Fallback: check by filename convention (for classes loaded by prior includes)
        $className = pathinfo($filePath, PATHINFO_FILENAME);
        if (class_exists($className) && is_a($className, SeederInterface::class, true)) {
            return $this->objectManager->create($className);
        }

        return null;
    }

    private function processDataFile(string $filePath): ?SeederInterface
    {
        foreach ($this->parsers as $parser) {
            if (!$parser->supports($filePath)) {
                continue;
            }

            try {
                $config = $parser->parse($filePath);
            } catch (\Throwable $e) {
                $this->logger->warning('Skipping invalid seeder file', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            return new ArraySeederAdapter($config, $this->handlerPool, $this->generateRunner);
        }

        return null;
    }
}
