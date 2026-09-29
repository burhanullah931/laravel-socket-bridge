<?php

namespace Burhan\SocketBridge;

final readonly class SocketEvent
{
    public function __construct(
        public string|int $userId,
        public array $payload,
        public string $name,
    ) {}
}
