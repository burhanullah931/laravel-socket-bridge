# Laravel Socket Bridge

Reusable Laravel-side integration for authenticated Socket.IO events transported through Redis.

This package is intentionally PHP-only. The Socket.IO Node.js gateway remains a separate service
because Composer packages cannot manage Node.js dependencies. The companion gateway is included
under the `node/` directory and is installed with npm.

## Installation

```bash
composer require burhan/laravel-socket-bridge
php artisan vendor:publish --tag=socket-bridge-config
```

## Register an event

```php
use Burhan\SocketBridge\SocketEventRegistry;

public function boot(SocketEventRegistry $socketEvents): void
{
    $socketEvents->listen('UpdateMallChallengeProgress', function (array $payload, int|string $userId) {
        return app(MallChallengeProgressService::class)->updateProgress(
            $payload,
            User::findOrFail($userId),
        );
    });
}
```

The application-specific service remains responsible for validating the payload and applying
business logic. The package is responsible for the reusable Socket.IO/Redis integration layer.

## Node.js gateway

```bash
cd node
npm install
npm start
```
