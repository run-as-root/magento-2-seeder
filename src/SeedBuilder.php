<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder;

use RunAsRoot\Seeder\Api\SubtypeAwareInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeedBuilder
{
    private int $count = 1;

    /** @var array<string, mixed> */
    private array $with = [];

    /** @var (callable(int, \Faker\Generator): array)|null */
    private $using = null;

    private ?string $subtype = null;

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

    public function with(array $data): self
    {
        $this->with = $data;
        return $this;
    }

    public function using(callable $fn): self
    {
        $this->using = $fn;
        return $this;
    }

    public function subtype(string $subtype): self
    {
        $this->subtype = $subtype;
        return $this;
    }

    /** @return int[] created ids */
    public function create(): array
    {
        $parts = explode('.', $this->type, 2);
        $baseType = $parts[0];
        $dottedSubtype = $parts[1] ?? null;
        $effectiveSubtype = $this->subtype ?? $dottedSubtype;

        $handler = $this->handlers->get($baseType);
        $hasGenerator = $this->generators->has($baseType);

        if (!$hasGenerator && $this->with === [] && $this->using === null) {
            throw new \RuntimeException(
                "No data generator for type \"{$baseType}\"; pass data via ->with(...)"
            );
        }

        $generator = $hasGenerator ? $this->generators->get($baseType) : null;
        $faker = $this->fakerFactory->create();

        $subtypeAware = $effectiveSubtype !== null
            && $generator instanceof SubtypeAwareInterface;
        if ($subtypeAware) {
            $generator->setForcedSubtype($effectiveSubtype);
        }

        try {
            $ids = [];
            for ($i = 0; $i < $this->count; $i++) {
                $data = $generator !== null
                    ? $generator->generate($faker, $this->registry)
                    : [];
                $data = array_replace($data, $this->with);
                if ($this->using !== null) {
                    $data = array_replace($data, ($this->using)($i, $faker));
                }
                $ids[] = $handler->create($data);
            }
            return $ids;
        } finally {
            if ($subtypeAware) {
                $generator->setForcedSubtype(null);
            }
        }
    }
}
