# Laravel Socket.IO Bridge

Reusable Laravel integration for authenticated Socket.IO events transported through Redis.

The package contains the Laravel integration layer. The companion Socket.IO gateway is included
in the [`node/`](node/) directory and runs as a separate Node.js service.

## Requirements

- PHP 8.2 or newer
- Laravel 10 or newer
- Redis 6 or newer
- Node.js 20 or newer for the Socket.IO gateway

## Installation

Install the package with Composer:

```bash
composer require socket-bridge/laravel-socketio
```

Publish the package configuration:

```bash
php artisan vendor:publish --tag=socket-bridge-config
```

The package service provider is registered automatically through Laravel package discovery.

## Configuration

The published configuration file is:

```text
config/socket-bridge.php
```

Redis settings can be configured through environment variables:

```dotenv
SOCKET_BRIDGE_REDIS_URL=redis://127.0.0.1:6379
SOCKET_BRIDGE_REQUESTS_CHANNEL=socket:requests
SOCKET_BRIDGE_RESPONSES_CHANNEL=socket:responses
SOCKET_BRIDGE_USER_EVENTS_CHANNEL=socket:user-events
```

If `SOCKET_BRIDGE_REDIS_URL` is not set, the package falls back to `REDIS_URL` and then to the
default Redis URL defined in the configuration file.

## Registering an event

Register application-specific event handlers through `SocketEventRegistry`:

```php
use Burhan\SocketBridge\SocketEventRegistry;

public function boot(SocketEventRegistry $socketEvents): void
{
    $socketEvents->listen('UpdateProgress', function (array $payload, int|string $userId) {
        // Validate the payload and apply your application logic here.
        return [
            'user_id' => $userId,
            'payload' => $payload,
        ];
    });
}
```

The application remains responsible for authorization, payload validation, and business logic.

## Socket.IO gateway

The Node.js gateway authenticates Socket.IO connections through Laravel over Redis and forwards
events to the registered Laravel handlers.

Start the gateway locally:

```bash
cd node
npm install
REDIS_URL=redis://127.0.0.1:6379 npm start
```

The gateway listens on port `6006` by default. Configure it with:

```dotenv
REDIS_URL=redis://127.0.0.1:6379
SOCKET_IO_PORT=6006
SOCKET_IO_CORS_ORIGIN=http://localhost:3000
```

Alternatively, use `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASSWORD` instead of `REDIS_URL`.

## Testing

Check PHP syntax:

```bash
for file in src/*.php config/*.php; do php -l "$file" || exit 1; done
```

Validate the Composer package:

```bash
composer validate --strict
```

Start Redis and the gateway, then connect a Socket.IO client using an access token. The client
should authenticate successfully and receive responses from the registered Laravel event handler.

## Development

Install the Node.js dependencies:

```bash
cd node
npm install
```

Run the gateway:

```bash
npm start
```

## Releasing

Create and push a version tag after committing changes:

```bash
git tag -a v0.1.0 -m "Initial release"
git push origin v0.1.0
```

Packagist uses Git tags as Composer package versions.

## License

This package is released under the MIT license.
