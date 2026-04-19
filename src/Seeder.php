<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder;

use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

abstract class Seeder implements SeederInterface
{
    public function __construct(
        protected readonly EntityHandlerPool $handlers,
        protected readonly DataGeneratorPool $generators,
        protected readonly FakerFactory $fakerFactory,
        protected readonly GeneratedDataRegistry $registry,
    ) {
    }

    protected function customers(): SeedBuilder  { return $this->makeBuilder('customer'); }
    protected function products(): SeedBuilder   { return $this->makeBuilder('product'); }
    protected function orders(): SeedBuilder     { return $this->makeBuilder('order'); }
    protected function categories(): SeedBuilder { return $this->makeBuilder('category'); }
    protected function cms(): SeedBuilder        { return $this->makeBuilder('cms'); }

    protected function seed(string $type): SeedBuilder
    {
        return $this->makeBuilder($type);
    }

    private function makeBuilder(string $type): SeedBuilder
    {
        return new SeedBuilder(
            $type,
            $this->handlers,
            $this->generators,
            $this->fakerFactory,
            $this->registry,
        );
    }

    abstract public function getType(): string;
    abstract public function getOrder(): int;
    abstract public function run(): void;
}
