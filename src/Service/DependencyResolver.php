<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Service;

class DependencyResolver
{
    public function __construct(
        private readonly DataGeneratorPool $generatorPool,
    ) {
    }

    /** @return array<string, int> type => count, sorted by execution order */
    public function resolve(array $requestedCounts): array
    {
        $resolved = $requestedCounts;

        $queue = array_keys($requestedCounts);
        while ($queue !== []) {
            $type = array_shift($queue);

            if (!$this->generatorPool->has($type)) {
                continue;
            }

            $generator = $this->generatorPool->get($type);

            foreach ($generator->getDependencies() as $depType) {
                $depCount = $generator->getDependencyCount($depType, $resolved[$type]);

                if (!isset($resolved[$depType])) {
                    $resolved[$depType] = $depCount;
                    $queue[] = $depType;
                } elseif (!isset($requestedCounts[$depType])) {
                    $resolved[$depType] = max($resolved[$depType], $depCount);
                }
            }
        }

        uksort($resolved, function (string $a, string $b): int {
            $orderA = $this->generatorPool->has($a) ? $this->generatorPool->get($a)->getOrder() : 100;
            $orderB = $this->generatorPool->has($b) ? $this->generatorPool->get($b)->getOrder() : 100;

            return $orderA <=> $orderB;
        });

        return $resolved;
    }
}
