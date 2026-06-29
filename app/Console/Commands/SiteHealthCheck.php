<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SiteHealthCheck extends Command
{
    protected $signature = 'site:health-check {--url= : URL a validar}';

    protected $description = 'Valida se o site esta online e grava o ultimo resultado.';

    public function handle(): int
    {
        $url = (string) ($this->option('url') ?: config('operations.health_url'));

        if ($url === '') {
            $this->error('Configura OPERATIONS_HEALTH_URL ou APP_URL.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $ok = false;
        $status = null;
        $error = null;

        try {
            $response = Http::timeout(20)->get($url);
            $status = $response->status();
            $ok = $response->successful();
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $payload = [
            'ok' => $ok,
            'url' => $url,
            'status' => $status,
            'error' => $error,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'checked_at' => now()->toDateTimeString(),
        ];

        $path = (string) config('operations.health_scan_path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
