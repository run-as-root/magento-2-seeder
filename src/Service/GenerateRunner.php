<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Psr\Log\LoggerInterface;

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

    /** @return array<array{type: string, success: bool, count: int, failed: int, error: ?string}> */
    public function run(GenerateRunConfig $config): array
    {
        $this->registry->reset();

        $faker = $this->fakerFactory->create($config->locale, $config->seed);
        $resolvedCounts = $this->resolver->resolve($config->counts);

        if ($config->fresh) {
            $this->cleanTypes(array_keys($resolvedCounts));
        }

        $results = [];
        foreach ($resolvedCounts as $type => $count) {
            $results[] = $this->generateType($type, $count, $faker, $config->stopOnError);
        }

        return $results;
    }

    /** @return array{type: string, success: bool, count: int, failed: int, error: ?string} */
    private function generateType(string $type, int $count, \Faker\Generator $faker, bool $stopOnError): array
    {
        $generator = $this->generatorPool->get($type);
        $handler = $this->handlerPool->get($type);

        $created = 0;
        $failed = 0;
        $lastError = null;
        for ($i = 0; $i < $count; $i++) {
            try {
                $data = $generator->generate($faker, $this->registry);
                $handler->create($data);
                $this->registry->add($type, $data);
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
                    return [
                        'type' => $type,
                        'success' => false,
                        'count' => $created,
                        'failed' => $failed,
                        'error' => $lastError,
                    ];
                }
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
        $reversed = array_reverse($types);

        foreach ($reversed as $type) {
            if ($this->handlerPool->has($type)) {
                $this->handlerPool->get($type)->clean();
            }
        }
    }
}
