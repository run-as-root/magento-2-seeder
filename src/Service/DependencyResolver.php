<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

class DependencyResolver
{
    public function __construct(
        private readonly DataGeneratorPool $generatorPool,
    ) {
    }

    /** @return array<string, int> type => count, sorted by execution order */
    public function resolve(array $requestedCounts): array
    {
        // Aggregate dotted keys (e.g. product.bundle) into their base type for dep math,
        // but keep dotted keys intact in the output so the runner knows to force the subtype.
        $baseCounts = [];
        foreach ($requestedCounts as $type => $count) {
            $base = $this->baseType($type);
            $baseCounts[$base] = ($baseCounts[$base] ?? 0) + $count;
        }

        $resolved = $baseCounts;

        $queue = array_keys($baseCounts);
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
                } elseif (!isset($baseCounts[$depType])) {
                    $resolved[$depType] = max($resolved[$depType], $depCount);
                }
            }
        }

        // Restore user-requested counts (plain and dotted) in the output. Aggregated
        // base-type counts were only used for dependency math and must not leak into
        // the generation plan.
        foreach ($requestedCounts as $type => $count) {
            $resolved[$type] = $count;
        }

        // If a base type was only added as an aggregate (never requested directly),
        // remove it so we don't double-generate.
        foreach (array_keys($resolved) as $type) {
            if (!$this->isDotted($type)
                && !array_key_exists($type, $requestedCounts)
                && $this->baseTypeExistsOnlyAsDotted($type, $requestedCounts)
            ) {
                unset($resolved[$type]);
            }
        }

        uksort($resolved, function (string $a, string $b): int {
            $orderA = $this->orderFor($a);
            $orderB = $this->orderFor($b);

            return $orderA <=> $orderB;
        });

        return $resolved;
    }

    private function baseType(string $type): string
    {
        $parts = explode('.', $type, 2);

        return $parts[0];
    }

    private function isDotted(string $type): bool
    {
        return str_contains($type, '.');
    }

    /**
     * True when $baseType is not explicitly in $requestedCounts, but at least one
     * dotted variant of it is (e.g. base=product, requested has product.bundle only).
     *
     * @param array<string, int> $requestedCounts
     */
    private function baseTypeExistsOnlyAsDotted(string $baseType, array $requestedCounts): bool
    {
        if (array_key_exists($baseType, $requestedCounts)) {
            return false;
        }

        foreach (array_keys($requestedCounts) as $type) {
            if ($this->isDotted($type) && $this->baseType($type) === $baseType) {
                return true;
            }
        }

        return false;
    }

    private function orderFor(string $type): int
    {
        $base = $this->baseType($type);

        return $this->generatorPool->has($base) ? $this->generatorPool->get($base)->getOrder() : 100;
    }
}
