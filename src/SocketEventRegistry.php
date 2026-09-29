<?php

namespace Burhan\SocketBridge;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class SocketEventRegistry
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function listen(string $eventName, callable $handler): self
    {
        if (trim($eventName) === '') {
            throw new InvalidArgumentException('A socket event name is required.');
        }

        $this->handlers[$eventName] = $handler;

        return $this;
    }

    public function handlerFor(string $eventName): ?callable
    {
        return $this->handlers[$eventName] ?? null;
    }

    /** @return array<string, callable> */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Discover application listeners whose class names start with HandleSocket.
     *
     * HandleSocketTestEvent becomes the testEvent socket event.
     */
    public function discover(
        string $directory,
        string $namespace,
        Container $container,
        string $classPrefix = 'HandleSocket',
    ): self {
        if (! is_dir($directory)) {
            return $this;
        }

        $directory = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $namespace = trim($namespace, '\\').'\\';

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($directory));
            $className = $namespace.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath,
            );

            if (! str_starts_with(class_basename($className), $classPrefix)
                || ! class_exists($className)) {
                continue;
            }

            $listener = new ReflectionClass($className);

            if ($listener->isAbstract() || ! $listener->hasMethod('handle')) {
                continue;
            }

            $eventName = lcfirst(substr(class_basename($className), strlen($classPrefix)));

            if ($eventName === '') {
                continue;
            }

            $this->listen($eventName, function (array $payload, int|string $userId) use ($container, $className) {
                return $container->make($className)->handle($payload, $userId);
            });
        }

        return $this;
    }
}
