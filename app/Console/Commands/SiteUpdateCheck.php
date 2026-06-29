<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class SiteUpdateCheck extends Command
{
    protected $signature = 'site:update-check';

    protected $description = 'Verifica dependencias desatualizadas sem aplicar updates.';

    public function handle(): int
    {
        $results = [
            'composer' => $this->runProcess(['composer', 'outdated', '--format=json', '--direct']),
            'npm' => $this->runProcess($this->npmCommand()),
            'checked_at' => now()->toDateTimeString(),
        ];

        $path = (string) config('operations.updates_scan_path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line("Verificacao gravada em {$path}");

        return self::SUCCESS;
    }

    private function runProcess(array $command): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->run();

        return [
            'command' => implode(' ', $command),
            'exit_code' => $process->getExitCode(),
            'output' => trim($process->getOutput()),
            'error' => trim($process->getErrorOutput()),
        ];
    }

    private function npmCommand(): array
    {
        return PHP_OS_FAMILY === 'Windows'
            ? ['cmd', '/c', 'npm', 'outdated', '--json']
            : ['npm', 'outdated', '--json'];
    }
}
