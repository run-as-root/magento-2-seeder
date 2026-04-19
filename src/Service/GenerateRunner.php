<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\Api\SubtypeAwareInterface;

class GenerateRunner
{
    public function __construct(
        private readonly DataGeneratorPool $generatorPool,
        private readonly EntityHandlerPool $handlerPool,
        private readonly DependencyResolver $resolver,
        private readonly FakerFactory $fakerFactory,
        private readonly GeneratedDataRegistry $registry,
        private readonly LoggerInterface $logger,
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
        try {
            for ($i = 0; $i < $count; $i++) {
                try {
                    $data = $generator->generate($faker, $this->registry);
                    $data['id'] = $handler->create($data);
                    $this->registry->add($baseType, $data);
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    $lastError = $e->getMessage();
                    $this->logger->error('Generate failed', [
                        'type' => $type,
                        'iteration' => $i,
                        'exception' => $e,
                    ]);

                    if ($stopOnError) {
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
