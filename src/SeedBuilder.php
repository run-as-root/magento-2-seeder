<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder;

use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeedBuilder
{
    private int $count = 1;

    public function __construct(
        private readonly string $type,
        private readonly EntityHandlerPool $handlers,
        private readonly DataGeneratorPool $generators,
        private readonly FakerFactory $fakerFactory,
        private readonly GeneratedDataRegistry $registry,
    ) {
    }

    public function count(int $n): self
    {
        $this->count = $n;
        return $this;
    }

    /** @return int[] created ids */
    public function create(): array
    {
        $baseType = explode('.', $this->type, 2)[0];
        $handler = $this->handlers->get($baseType);
        $generator = $this->generators->get($baseType);
        $faker = $this->fakerFactory->create();

        $ids = [];
        for ($i = 0; $i < $this->count; $i++) {
            $data = $generator->generate($faker, $this->registry);
            $ids[] = $handler->create($data);
        }

        return $ids;
    }
}
