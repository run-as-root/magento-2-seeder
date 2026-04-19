<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order;

class StateTransitionPool
{
    /** @var array<string, StateTransitionInterface> */
    private array $transitions;

    /** @param array<string, StateTransitionInterface> $transitions */
    public function __construct(array $transitions = [])
    {
        foreach ($transitions as $key => $transition) {
            if (!$transition instanceof StateTransitionInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Transition for "%s" must implement %s', $key, StateTransitionInterface::class)
                );
            }
        }
        $this->transitions = $transitions;
    }

    public function has(string $state): bool
    {
        return isset($this->transitions[$state]);
    }

    public function get(string $state): StateTransitionInterface
    {
        if (!isset($this->transitions[$state])) {
            throw new \InvalidArgumentException("No state transition registered for state: {$state}");
        }

        return $this->transitions[$state];
    }

    /** @return string[] */
    public function getStates(): array
    {
        return array_keys($this->transitions);
    }
}
