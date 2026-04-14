<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Service;

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

    /** @return array<array{type: string, success: bool, count: int, error?: string}> */
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

    /** @return array{type: string, success: bool, count: int, error?: string} */
    private function generateType(string $type, int $count, \Faker\Generator $faker, bool $stopOnError): array
    {
        $generator = $this->generatorPool->get($type);
        $handler = $this->handlerPool->get($type);

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $data = $generator->generate($faker, $this->registry);
                $handler->create($data);
                $this->registry->add($type, $data);
                $created++;
            } catch (\Throwable $e) {
                $this->logger->error('Generate failed', [
                    'type' => $type,
                    'iteration' => $i,
                    'exception' => $e,
                ]);

                if ($stopOnError) {
                    return ['type' => $type, 'success' => false, 'count' => $created, 'error' => $e->getMessage()];
                }
            }
        }

        return ['type' => $type, 'success' => true, 'count' => $created];
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
