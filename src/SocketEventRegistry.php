<?php

namespace Burhan\SocketBridge;

use InvalidArgumentException;

final class SocketEventRegistry
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function listen(string $eventName, callable $handler): self
    {
        if (trim($eventName) === '') {
            throw new InvalidArgumentException('A socket event name is required.');
        }

        $this->handlers[$eventName] = $handler;

        return $this;
    }

    public function handlerFor(string $eventName): ?callable
    {
        return $this->handlers[$eventName] ?? null;
    }

    /** @return array<string, callable> */
    public function all(): array
    {
        return $this->handlers;
    }
}
