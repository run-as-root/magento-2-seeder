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

    /**
     * @param callable(string, int, int): void|null $onProgress Invoked by count-based adapters
     *                                                          with ($type, $done, $total).
     * @return array<array{type: string, success: bool, error?: string}>
     */
    public function run(SeederRunConfig $config, ?callable $onProgress = null): array
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
                $this->runSeeder($seeder, $onProgress);
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

    private function runSeeder(SeederInterface $seeder, ?callable $onProgress): void
    {
        if ($seeder instanceof ArraySeederAdapter && $seeder->hasCount()) {
            $seeder->setProgressCallback($onProgress);
            $seeder->run();

            return;
        }

        $this->runWithFeedback($seeder);
    }

    /**
     * Runs a non-count seeder with user feedback when attached to a TTY,
     * or silently when stdout is redirected (CI, cron, piped output).
     *
     * Guarding on {@see stream_isatty()} prevents Laravel Prompts' spinner
     * from emitting ANSI escape sequences into log files.
     */
    private function runWithFeedback(SeederInterface $seeder): void
    {
        if (!$this->isStdoutTty()) {
            $seeder->run();

            return;
        }

        \Laravel\Prompts\spin(
            static fn () => $seeder->run(),
            sprintf('Seeding %s', $seeder->getType()),
        );
    }

    private function isStdoutTty(): bool
    {
        return defined('STDOUT') && stream_isatty(STDOUT);
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
