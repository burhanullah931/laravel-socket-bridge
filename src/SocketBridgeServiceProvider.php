<?php

namespace Burhan\SocketBridge;

use Illuminate\Support\ServiceProvider;

final class SocketBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/socket-bridge.php', 'socket-bridge');

        $this->app->singleton(SocketEventRegistry::class, fn () => new SocketEventRegistry());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/socket-bridge.php' => config_path('socket-bridge.php'),
        ], 'socket-bridge-config');
    }
}
