<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\SeederInterface;
use Psr\Log\LoggerInterface;

class SeederRunner
{
    public function __construct(
        private readonly SeederDiscovery $discovery,
        private readonly EntityHandlerPool $handlerPool,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array<array{type: string, success: bool, error?: string}> */
    public function run(SeederRunConfig $config): array
    {
        $seeders = $this->discovery->discover();
        $seeders = $this->filterSeeders($seeders, $config);

        usort($seeders, static fn (SeederInterface $a, SeederInterface $b): int => $a->getOrder() <=> $b->getOrder());

        if ($config->fresh) {
            $this->cleanData($seeders);
        }

        $results = [];
        foreach ($seeders as $seeder) {
            try {
                $seeder->run();
                $results[] = ['type' => $seeder->getType(), 'success' => true];
            } catch (\Throwable $e) {
                $results[] = ['type' => $seeder->getType(), 'success' => false, 'error' => $e->getMessage()];
                $this->logger->error('Seeder failed', [
                    'type' => $seeder->getType(),
                    'exception' => $e,
                ]);

                if ($config->stopOnError) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * @param SeederInterface[] $seeders
     * @return SeederInterface[]
     */
    private function filterSeeders(array $seeders, SeederRunConfig $config): array
    {
        return array_values(array_filter(
            $seeders,
            static function (SeederInterface $seeder) use ($config): bool {
                if ($config->only !== [] && !in_array($seeder->getType(), $config->only, true)) {
                    return false;
                }

                if ($config->exclude !== [] && in_array($seeder->getType(), $config->exclude, true)) {
                    return false;
                }

                return true;
            }
        ));
    }

    /** @param SeederInterface[] $seeders */
    private function cleanData(array $seeders): void
    {
        $types = array_unique(array_map(
            static fn (SeederInterface $seeder): string => $seeder->getType(),
            $seeders
        ));

        $reversedTypes = array_reverse($types);

        foreach ($reversedTypes as $type) {
            if ($this->handlerPool->has($type)) {
                $this->handlerPool->get($type)->clean();
            }
        }
    }
}
