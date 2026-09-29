<?php

namespace Burhan\SocketBridge\Console;

use Illuminate\Console\Command;

final class InstallSocketBridgeCommand extends Command
{
    protected $signature = 'socket-bridge:install {--start : Start the gateway after installing dependencies}';

    protected $description = 'Install the Socket.IO gateway dependencies';

    public function handle(): int
    {
        $nodeVersion = $this->version('node');
        $npmVersion = $this->version('npm');

        if ($nodeVersion === null || $npmVersion === null) {
            $this->error('Node.js and npm are required to install the Socket.IO gateway.');
            $this->line('Install Node.js 20 or newer, then run this command again.');

            return self::FAILURE;
        }

        $gatewayPath = realpath(__DIR__.'/../../node');

        if ($gatewayPath === false || ! is_file($gatewayPath.'/package.json')) {
            $this->error('The Socket.IO gateway files could not be found.');

            return self::FAILURE;
        }

        $this->info("Detected {$nodeVersion} and {$npmVersion}.");
        $this->info('Installing Socket.IO gateway dependencies...');

        if (! $this->run('npm', ['install'], $gatewayPath)) {
            $this->error('The Socket.IO gateway dependencies could not be installed.');

            return self::FAILURE;
        }

        $this->info('Socket.IO gateway dependencies installed successfully.');

        if (! $this->option('start')) {
            $this->line('Start the gateway with:');
            $this->line('  cd node && npm start');

            return self::SUCCESS;
        }

        $this->info('Starting the Socket.IO gateway. Press Ctrl+C to stop it.');

        return $this->run('npm', ['start'], $gatewayPath) ? self::SUCCESS : self::FAILURE;
    }

    private function version(string $binary): ?string
    {
        $output = $this->process([$binary, '--version']);

        return $output['exitCode'] === 0 ? trim($output['stdout']) : null;
    }

    /** @param list<string> $command */
    private function run(string $binary, array $arguments, string $workingDirectory): bool
    {
        $result = $this->process([$binary, ...$arguments], $workingDirectory);

        if ($result['stdout'] !== '') {
            $this->line($result['stdout']);
        }

        if ($result['stderr'] !== '') {
            $this->error($result['stderr']);
        }

        return $result['exitCode'] === 0;
    }

    /** @param list<string> $command @return array{exitCode: int, stdout: string, stderr: string} */
    private function process(array $command, ?string $workingDirectory = null): array
    {
        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $workingDirectory);

        if (! is_resource($process)) {
            return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'Unable to start the process.'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout ?: '',
            'stderr' => $stderr ?: '',
        ];
    }
}
