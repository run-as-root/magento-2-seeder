<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\Api\SubtypeAwareInterface;

class GenerateRunner
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly DataGeneratorPool $generatorPool,
        private readonly EntityHandlerPool $handlerPool,
        private readonly DependencyResolver $resolver,
        private readonly FakerFactory $fakerFactory,
        private readonly GeneratedDataRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly ?ResourceConnection $resource = null,
    ) {
    }

    /**
     * @param callable(string, int, int): void|null $onProgress Invoked after each iteration with
     *                                                          ($type, $done, $total).
     * @return array<array{type: string, success: bool, count: int, failed: int, error: ?string}>
     */
    public function run(GenerateRunConfig $config, ?callable $onProgress = null): array
    {
        $this->registry->reset();

        $faker = $this->fakerFactory->create($config->locale, $config->seed);
        $resolvedCounts = $this->resolver->resolve($config->counts);

        if ($config->fresh) {
            $this->cleanTypes(array_keys($resolvedCounts));
        }

        $results = [];
        foreach ($resolvedCounts as $type => $count) {
            $results[] = $this->generateType($type, $count, $faker, $config->stopOnError, $onProgress);
        }

        return $results;
    }

    /**
     * @param callable(string, int, int): void|null $onProgress
     * @return array{type: string, success: bool, count: int, failed: int, error: ?string}
     */
    private function generateType(
        string $type,
        int $count,
        \Faker\Generator $faker,
        bool $stopOnError,
        ?callable $onProgress = null
    ): array {
        $parts = explode('.', $type, 2);
        $baseType = $parts[0];
        $subtype = $parts[1] ?? null;

        $generator = $this->generatorPool->get($baseType);
        $handler = $this->handlerPool->get($baseType);

        $subtypeAware = $subtype !== null && $generator instanceof SubtypeAwareInterface;
        if ($subtypeAware) {
            $generator->setForcedSubtype($subtype);
        }

        $created = 0;
        $failed = 0;
        $lastError = null;
        $connection = $this->resource?->getConnection();
        $sinceLastCommit = 0;
        $connection?->beginTransaction();

        try {
            for ($i = 0; $i < $count; $i++) {
                try {
                    $data = $generator->generate($faker, $this->registry);
                    $data['id'] = $handler->create($data);
                    $this->registry->add($baseType, $data);
                    $created++;
                    $sinceLastCommit++;

                    if ($connection !== null && $sinceLastCommit >= self::BATCH_SIZE) {
                        $connection->commit();
                        $connection->beginTransaction();
                        $sinceLastCommit = 0;
                    }
                } catch (\Throwable $e) {
                    if ($connection !== null) {
                        try {
                            $connection->rollBack();
                        } catch (\Throwable) {
                        }
                        $connection->beginTransaction();
                        $sinceLastCommit = 0;
                    }

                    $failed++;
                    $lastError = $e->getMessage();
                    $this->logger->error('Generate failed', [
                        'type' => $type,
                        'iteration' => $i,
                        'exception' => $e,
                    ]);

                    if ($stopOnError) {
                        $connection?->commit();

                        if ($onProgress !== null) {
                            $onProgress($type, $created + $failed, $count);
                        }

                        return [
                            'type' => $type,
                            'success' => false,
                            'count' => $created,
                            'failed' => $failed,
                            'error' => $lastError,
                        ];
                    }
                }

                if ($onProgress !== null) {
                    $onProgress($type, $created + $failed, $count);
                }
            }

            $connection?->commit();
        } catch (\Throwable $e) {
            try {
                $connection?->rollBack();
            } catch (\Throwable) {
            }
            throw $e;
        } finally {
            if ($subtypeAware && $generator instanceof SubtypeAwareInterface) {
                $generator->setForcedSubtype(null);
            }
        }

        return [
            'type' => $type,
            'success' => $failed === 0,
            'count' => $created,
            'failed' => $failed,
            'error' => $lastError,
        ];
    }

    private function cleanTypes(array $types): void
    {
        $baseTypes = [];
        foreach ($types as $type) {
            $baseTypes[] = explode('.', $type, 2)[0];
        }
        $reversed = array_reverse(array_values(array_unique($baseTypes)));

        foreach ($reversed as $baseType) {
            if ($this->handlerPool->has($baseType)) {
                $this->handlerPool->get($baseType)->clean();
            }
        }
    }
}
