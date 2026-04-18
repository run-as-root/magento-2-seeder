<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\SeederInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;

class SeederDiscovery
{
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly ObjectManagerInterface $objectManager,
        private readonly EntityHandlerPool $handlerPool,
        private readonly GenerateRunner $generateRunner,
    ) {
    }

    /** @return SeederInterface[] */
    public function discover(): array
    {
        $seedersDir = $this->directoryList->getRoot() . '/dev/seeders';

        if (!is_dir($seedersDir)) {
            return [];
        }

        $files = glob($seedersDir . '/*Seeder.php');
        if ($files === false || $files === []) {
            return [];
        }

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
}
