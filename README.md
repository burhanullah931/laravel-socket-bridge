# Laravel Socket.IO Bridge

Reusable Laravel integration for authenticated Socket.IO events transported through Redis.

The package contains the Laravel integration layer. The companion Socket.IO gateway is included
in the [`node/`](node/) directory and runs as a separate Node.js service.

## Quick start

Run these steps in order:

```bash
composer require socket-bridge/laravel-socketio
php artisan vendor:publish --tag=socket-bridge-config
php artisan socket-bridge:install
```

Then:

1. Add the Redis settings shown below to `.env`.
2. Register `testEvent` in `AppServiceProvider`.
3. Start Redis and Laravel.
4. Start the gateway with `php artisan socket-bridge:install --start`.
5. Connect a Socket.IO client with a valid access token.

`socket-bridge:install` only checks Node.js/npm and installs dependencies. The `--start` option
runs the gateway in the current terminal. For production, use Supervisor so it starts and
restarts automatically.

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

After installation, use the package command to verify Node.js and npm and install the gateway
dependencies:

```bash
php artisan socket-bridge:install
```

If Node.js or npm is not installed, the command stops and asks you to install Node.js 20 or
newer before running it again. To install the dependencies and run the gateway in the foreground:

```bash
php artisan socket-bridge:install --start
```

For production, run the install command without `--start`, then use the Supervisor configuration
below so the gateway is monitored and restarted automatically.

### Available Artisan commands

| Command | Purpose |
| --- | --- |
| `php artisan socket-bridge:install` | Checks Node.js/npm and installs the gateway dependencies. |
| `php artisan socket-bridge:install --start` | Installs dependencies and starts the gateway in the foreground. |

There is currently no `php artisan socket-bridge:test` command. Testing is done by registering the
`testEvent` example below, starting Redis, Laravel, and the gateway, and then emitting
`testEvent` from a Socket.IO client.

## Updating the package

For an existing Laravel application, update the package with Composer:

```bash
composer update socket-bridge/laravel-socketio
```

After updating, run the installer again so the gateway dependencies match the package version:

```bash
php artisan socket-bridge:install
```

If the published configuration file has changed, republish it after reviewing your local
settings:

```bash
php artisan vendor:publish --tag=socket-bridge-config --force
```

Restart the gateway after an update. If Supervisor manages it:

```bash
sudo supervisorctl restart socket-bridge-gateway
```

Commit `composer.lock` in the Laravel application when using a locked deployment workflow.

## Configuration

The published configuration file is:

```text
config/socket-bridge.php
```

Redis settings can be configured through environment variables:

```dotenv
SOCKET_BRIDGE_REDIS_URL=redis://127.0.0.1:6379
SOCKET_BRIDGE_REQUESTS_CHANNEL=qsfa:socket:requests
SOCKET_BRIDGE_RESPONSES_CHANNEL=qsfa:socket:responses
SOCKET_BRIDGE_USER_EVENTS_CHANNEL=qsfa:socket:user-events
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

## Create a test event

Register a simple event in a service provider, such as `AppServiceProvider`:

```php
use Burhan\SocketBridge\SocketEventRegistry;

public function boot(SocketEventRegistry $socketEvents): void
{
    $socketEvents->listen('testEvent', function (array $payload, int|string $userId) {
        return [
            'message' => 'testEvent received',
            'user_id' => $userId,
            'payload' => $payload,
        ];
    });
}
```

From a Socket.IO client, emit the event after connecting with a valid access token:

```js
socket.emit('testEvent', { message: 'Hello from Socket.IO' }, (response) => {
    console.log(response);
});

socket.on('testEvent.response', (response) => {
    console.log(response);
});
```

The handler receives the event payload and authenticated user ID. Return an array to send a
response to the client. Add authorization and payload validation inside the handler or in your
application's existing authorization layer.

This example is a socket event handler, not a Laravel `ShouldQueue` event/listener pair. Do not
use `php artisan make:event` or `php artisan make:listener` for this integration; register socket
events through `SocketEventRegistry` as shown above.

### Test the event

Run the following in separate terminals:

```bash
# Terminal 1
redis-server

# Terminal 2
php artisan serve

# Terminal 3
php artisan socket-bridge:install --start
```

Then connect a Socket.IO client with a valid access token and emit `testEvent`. A successful test
returns the `message`, `user_id`, and `payload` from the PHP handler. If the test fails, check the
access token, Redis URL, matching channel names, gateway port, and CORS origin.

## Server setup

The bridge requires Redis, the Laravel application, and the Node.js Socket.IO gateway:

1. Start Redis.
2. Install and configure the Laravel package.
3. Start the Socket.IO gateway from the package's `node/` directory.

Example local setup:

```bash
# Terminal 1: Redis
redis-server

# Terminal 2: Laravel application
php artisan serve

# Terminal 3: Socket.IO gateway
cd node
npm install
REDIS_URL=redis://127.0.0.1:6379 SOCKET_IO_PORT=6006 npm start
```

The gateway listens on port `6006` by default. Configure the connection with:

```dotenv
REDIS_URL=redis://127.0.0.1:6379
SOCKET_IO_PORT=6006
SOCKET_IO_CORS_ORIGIN=http://localhost:3000
```

The Laravel and gateway Redis channel settings must match. If you change the Laravel
`SOCKET_BRIDGE_*_CHANNEL` values, set the corresponding variables for the Node.js gateway too.

## Run the gateway automatically

For production, use a process manager so the gateway starts on boot and restarts if it exits.
For example, install Supervisor and create `/etc/supervisor/conf.d/socket-bridge-gateway.conf`:

```ini
[program:socket-bridge-gateway]
directory=/var/www/your-app/node
command=/usr/bin/npm start
autostart=true
autorestart=true
startsecs=5
user=www-data
environment=NODE_ENV="production",REDIS_URL="redis://127.0.0.1:6379",SOCKET_IO_PORT="6006",SOCKET_IO_CORS_ORIGIN="https://your-app.example"
stdout_logfile=/var/log/socket-bridge-gateway.log
stderr_logfile=/var/log/socket-bridge-gateway-error.log
stopasgroup=true
killasgroup=true
```

Replace `/var/www/your-app`, `www-data`, the Redis URL, and the allowed origin with your
deployment values. Then enable the service:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status socket-bridge-gateway
```

Useful management commands:

```bash
sudo supervisorctl restart socket-bridge-gateway
sudo supervisorctl stop socket-bridge-gateway
sudo supervisorctl tail -f socket-bridge-gateway
```

Do not start the gateway from a Laravel service provider or an HTTP request. It is a long-running
service and should be monitored independently from PHP.

## Troubleshooting

- `ECONNREFUSED`: confirm that Redis is running and that `REDIS_URL` points to the correct host
  and port.
- `Laravel Redis listener timed out`: confirm that Laravel is running and listening on the same
  Redis request channel as the gateway.
- CORS errors: set `SOCKET_IO_CORS_ORIGIN` to the exact origin of the web client.
- Event responses are missing: verify that the event name passed to `listen()` exactly matches the
  name emitted by the client and that all Redis channel names match.
- Gateway stops after logout or reboot: use Supervisor or another process manager and inspect its
  log files.

## License

This package is released under the MIT license.
