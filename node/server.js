import { Server } from 'socket.io';
import {
    authenticateSocket,
    createEventBus,
    registerSocketEvents,
} from './events.js';

const port = Number.parseInt(process.env.SOCKET_IO_PORT || '6006', 10);
const redisHost = process.env.REDIS_HOST || 'redis';
const redisPort = process.env.REDIS_PORT || '6379';
const redisPassword = process.env.REDIS_PASSWORD;
const redisUrl = process.env.REDIS_URL || `redis://${redisPassword ? `:${encodeURIComponent(redisPassword)}@` : ''}${redisHost}:${redisPort}`;
const corsOrigin = process.env.SOCKET_IO_CORS_ORIGIN || '*';

if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error('SOCKET_IO_PORT must be a valid TCP port');
}

const io = new Server({
    cors: {
        origin: corsOrigin === '*' ? true : corsOrigin.split(',').map((origin) => origin.trim()),
    },
});

const userSockets = new Map();
const eventBus = await createEventBus({
    redisUrl,
    onUserEvent: ({ event, userId, data }) => {
        const targetSockets = userSockets.get(String(userId)) || [];

        for (const targetSocket of targetSockets) {
            targetSocket.emit(event, data);
        }
    },
});

io.use(authenticateSocket({ eventBus }));

io.on('connection', (socket) => {
    registerSocketEvents(socket, { eventBus, userSockets });
});

io.listen(port);

io.engine.on('connection_error', (error) => {
    console.error('Socket.IO connection error:', error.message);
});

console.log(`Socket Bridge gateway listening on port ${port}`);
