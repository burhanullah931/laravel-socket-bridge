import { createClient } from 'redis';
import { randomUUID } from 'node:crypto';

const REQUESTS_CHANNEL = process.env.SOCKET_BRIDGE_REQUESTS_CHANNEL || 'qsfa:socket:requests';
const RESPONSES_CHANNEL = process.env.SOCKET_BRIDGE_RESPONSES_CHANNEL || 'qsfa:socket:responses';
const USER_EVENTS_CHANNEL = process.env.SOCKET_BRIDGE_USER_EVENTS_CHANNEL || 'qsfa:socket:user-events';
const REQUEST_TIMEOUT_MS = 10000;

export async function createEventBus({ redisUrl, onUserEvent }) {
    const publisher = createClient({ url: redisUrl });
    const subscriber = publisher.duplicate();
    const userEventSubscriber = publisher.duplicate();
    const pending = new Map();

    publisher.on('error', (error) => console.error('Redis publisher error:', error));
    subscriber.on('error', (error) => console.error('Redis subscriber error:', error));
    userEventSubscriber.on('error', (error) => console.error('Redis user-event subscriber error:', error));

    await Promise.all([
        publisher.connect(),
        subscriber.connect(),
        userEventSubscriber.connect(),
    ]);

    await subscriber.subscribe(RESPONSES_CHANNEL, (message) => {
        let response;

        try {
            response = JSON.parse(message);
        } catch {
            console.error('Ignoring invalid Redis response:', message);
            return;
        }

        const request = pending.get(response.requestId);

        if (request) {
            pending.delete(response.requestId);
            clearTimeout(request.timeout);
            request.resolve(response);
        }
    });

    await userEventSubscriber.subscribe(USER_EVENTS_CHANNEL, (message) => {
        try {
            onUserEvent?.(JSON.parse(message));
        } catch {
            console.error('Ignoring invalid Redis user event:', message);
        }
    });

    return {
        request(payload) {
            const requestId = randomUUID();

            return new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    pending.delete(requestId);
                    reject(new Error('Laravel Redis listener timed out'));
                }, REQUEST_TIMEOUT_MS);

                pending.set(requestId, { resolve, timeout });
                publisher.publish(REQUESTS_CHANNEL, JSON.stringify({
                    ...payload,
                    requestId,
                })).catch((error) => {
                    pending.delete(requestId);
                    clearTimeout(timeout);
                    reject(error);
                });
            });
        },
    };
}

function getAccessToken(socket) {
    const token = socket.handshake.auth?.token
        || socket.handshake.headers?.authorization
        || socket.handshake.headers?.token;

    if (typeof token !== 'string' || token.trim() === '') {
        throw new Error('Authentication token is required');
    }

    return token.replace(/^Bearer\s+/i, '').trim();
}

export function authenticateSocket({ eventBus }) {
    return async (socket, next) => {
        try {
            const token = getAccessToken(socket);
            const response = await eventBus.request({
                type: 'auth',
                socketId: socket.id,
                token,
            });

            if (!response.success) {
                throw new Error(response.error || 'Invalid or expired token');
            }

            socket.data.accessToken = token;
            socket.data.userId = response.userId;
            next();
        } catch (error) {
            next(new Error(error.message || 'Authentication failed'));
        }
    };
}

function emitError(socket, acknowledge, event, error, errors = {}) {
    const response = { success: false, error, errors };

    if (typeof acknowledge === 'function') {
        acknowledge(response);
    }

    socket.emit(event, response);
}

export function registerSocketEvents(socket, { eventBus, userSockets }) {
    const userId = String(socket.data.userId);
    const sockets = userSockets.get(userId) || new Set();
    sockets.add(socket);
    userSockets.set(userId, sockets);

    socket.onAny(async (eventName, ...args) => {
        const acknowledge = typeof args.at(-1) === 'function' ? args.pop() : null;
        const payload = args[0] || {};

        try {
            const response = await eventBus.request({
                type: eventName,
                socketId: socket.id,
                token: socket.data.accessToken,
                payload,
            });

            if (!response.success) {
                emitError(socket, acknowledge, `${eventName}.response`, response.error, response.errors);
                return;
            }

            if (typeof acknowledge === 'function') {
                acknowledge(response.data);
            }

            const targetSockets = userSockets.get(String(response.userId)) || new Set([socket]);
            for (const targetSocket of targetSockets) {
                targetSocket.emit(response.type || `${eventName}.response`, response.data);
            }
        } catch (error) {
            emitError(socket, acknowledge, `${eventName}.response`, error.message || 'Unable to process socket event');
        }
    });

    socket.once('disconnect', () => {
        sockets.delete(socket);

        if (sockets.size === 0) {
            userSockets.delete(userId);
        }
    });
}
