<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product;

class TypeBuilderPool
{
    /** @var array<string, TypeBuilderInterface> */
    private array $builders;

    /** @param array<string, TypeBuilderInterface> $builders */
    public function __construct(array $builders = [])
    {
        foreach ($builders as $key => $builder) {
            if (!$builder instanceof TypeBuilderInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Builder for "%s" must implement %s', $key, TypeBuilderInterface::class)
                );
            }
        }
        $this->builders = $builders;
    }

    public function has(string $type): bool
    {
        return isset($this->builders[$type]);
    }

    public function get(string $type): TypeBuilderInterface
    {
        if (!isset($this->builders[$type])) {
            throw new \InvalidArgumentException(sprintf('No type builder registered for type: %s', $type));
        }

        return $this->builders[$type];
    }

    /** @return string[] */
    public function getTypes(): array
    {
        return array_keys($this->builders);
    }
}
