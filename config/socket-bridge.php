<?php

return [
    'redis' => [
        'url' => env('SOCKET_BRIDGE_REDIS_URL', env('REDIS_URL', 'redis://redis:6379')),
        'requests_channel' => env('SOCKET_BRIDGE_REQUESTS_CHANNEL', 'qsfa:socket:requests'),
        'responses_channel' => env('SOCKET_BRIDGE_RESPONSES_CHANNEL', 'qsfa:socket:responses'),
        'user_events_channel' => env('SOCKET_BRIDGE_USER_EVENTS_CHANNEL', 'qsfa:socket:user-events'),
    ],

    'events' => [],
];
