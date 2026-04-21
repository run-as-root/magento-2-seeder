<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Api\SeederInterface;

class ArraySeederAdapter implements SeederInterface
{
    private const DEFAULT_ORDER = [
        'category' => 10,
        'product' => 20,
        'customer' => 30,
        'order' => 40,
        'cms' => 50,
    ];

    private ?\Closure $onProgress = null;

    public function __construct(
        private readonly array $config,
        private readonly EntityHandlerPool $handlerPool,
        private readonly ?GenerateRunner $generateRunner = null,
    ) {
    }

    public function getType(): string
    {
        return $this->config['type'];
    }

    public function getOrder(): int
    {
        return $this->config['order'] ?? self::DEFAULT_ORDER[$this->config['type']] ?? 100;
    }

    public function setProgressCallback(?callable $callback): void
    {
        $this->onProgress = $callback === null ? null : \Closure::fromCallable($callback);
    }

    public function hasCount(): bool
    {
        return isset($this->config['count']) && $this->generateRunner !== null;
    }

    public function run(): void
    {
        if (isset($this->config['count']) && $this->generateRunner !== null) {
            $config = new GenerateRunConfig(
                counts: [$this->config['type'] => $this->config['count']],
                locale: $this->config['locale'] ?? 'en_US',
                seed: $this->config['seed'] ?? null,
            );
            $this->generateRunner->run($config, $this->onProgress);

            return;
        }

        $handler = $this->handlerPool->get($this->config['type']);

        foreach ($this->config['data'] as $item) {
            $handler->create($item);
        }
    }
}
