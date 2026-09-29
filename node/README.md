# Socket.IO gateway

This is the Node.js companion service for the Laravel Socket.IO Bridge. It authenticates clients
through Laravel over Redis and forwards socket events to Laravel event handlers.

## Install and run

From the Laravel application root, the package can check for Node.js and install the gateway
dependencies:

```bash
php artisan socket-bridge:install
```

If Node.js or npm is missing, the command returns an error asking you to install Node.js 20 or
newer and run it again.

You can also install the dependencies directly:

```bash
npm install
```

Start the gateway:

```bash
npm start
```

Or install and start the gateway in the foreground with:

```bash
php artisan socket-bridge:install --start
```

## Configuration

The gateway supports these environment variables:

- `REDIS_URL`, or `REDIS_HOST`, `REDIS_PORT`, and optional `REDIS_PASSWORD`
- `SOCKET_IO_PORT` (default `6006`)
- `SOCKET_IO_CORS_ORIGIN` (default `*`)
- `SOCKET_BRIDGE_REQUESTS_CHANNEL` (default `qsfa:socket:requests`)
- `SOCKET_BRIDGE_RESPONSES_CHANNEL` (default `qsfa:socket:responses`)
- `SOCKET_BRIDGE_USER_EVENTS_CHANNEL` (default `qsfa:socket:user-events`)

Example:

```bash
REDIS_URL=redis://127.0.0.1:6379 \
SOCKET_IO_PORT=6006 \
SOCKET_IO_CORS_ORIGIN=http://localhost:3000 \
npm start
```

The Redis channel values must match the values configured in the Laravel application.

## Automatic startup

In production, run the gateway with Supervisor, systemd, Docker, or another process manager. Do
not launch it from a Laravel request. See the main [project README](../README.md#run-the-gateway-automatically)
for a complete Supervisor configuration.

## Testing

There is no separate `socket-bridge:test` command currently. Register `testEvent` in the Laravel
application as documented in the [main project README](../README.md#create-a-test-event), start
Redis, Laravel, and this gateway, then emit `testEvent` from a Socket.IO client with a valid access
token.
