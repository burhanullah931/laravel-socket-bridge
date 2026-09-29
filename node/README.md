# Socket.IO gateway

This is the Node.js companion to the Composer package. Install and run it with:

```bash
npm install
npm start
```

Required environment variables:

- `REDIS_URL`, or `REDIS_HOST` and `REDIS_PORT`
- `SOCKET_IO_PORT` (default `6006`)
- `SOCKET_IO_CORS_ORIGIN` (default `*`)

The gateway authenticates sockets through Laravel over Redis and forwards socket events to the
Laravel package listener.
