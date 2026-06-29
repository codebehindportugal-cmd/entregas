<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class SiteSecurityScan extends Command
{
    protected $signature = 'site:security-scan';

    protected $description = 'Executa composer audit e npm audit a partir do site.';

    public function handle(): int
    {
        $composer = $this->runProcess(['composer', 'audit', '--format=json']);
        $npm = $this->runProcess($this->npmCommand());
        $summary = $this->summarize($composer, $npm);
        $results = [
            'summary' => $summary,
            'composer' => $composer,
            'npm' => $npm,
            'scanned_at' => now()->toDateTimeString(),
        ];

        $path = (string) config('operations.security_scan_path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line("Scan gravado em {$path}");
        $this->line("Vulnerabilidades: {$summary['total']} total, {$summary['critical']} criticas, {$summary['high']} altas, {$summary['medium']} medias, {$summary['low']} baixas.");

        foreach ($summary['items'] as $item) {
            $this->line("[{$item['severity']}] {$item['source']} {$item['package']}: {$item['title']}");
            if (filled($item['url'] ?? null)) {
                $this->line("  {$item['url']}");
            }
        }

        return $summary['total'] > 0 ? self::FAILURE : self::SUCCESS;
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
            ? ['cmd', '/c', 'npm', 'audit', '--audit-level=moderate', '--json']
            : ['npm', 'audit', '--audit-level=moderate', '--json'];
    }

    private function summarize(array $composer, array $npm): array
    {
        $items = [
            ...$this->composerItems($composer['output']),
            ...$this->npmItems($npm['output']),
        ];

        $counts = collect($items)
            ->countBy(fn (array $item): string => $item['severity'])
            ->all();

        return [
            'total' => count($items),
            'critical' => $counts['critical'] ?? 0,
            'high' => $counts['high'] ?? 0,
            'medium' => $counts['medium'] ?? 0,
            'moderate' => $counts['moderate'] ?? 0,
            'low' => $counts['low'] ?? 0,
            'items' => $items,
        ];
    }

    private function composerItems(string $output): array
    {
        $data = json_decode($output, true);

        if (! is_array($data)) {
            return [];
        }

        return collect($data['advisories'] ?? [])
            ->flatMap(function (array $advisories, string $package): array {
                return collect($advisories)
                    ->map(fn (array $advisory): array => [
                        'source' => 'composer',
                        'package' => $package,
                        'severity' => strtolower((string) ($advisory['severity'] ?? 'unknown')),
                        'title' => (string) ($advisory['title'] ?? $advisory['cve'] ?? 'Vulnerabilidade sem titulo'),
                        'cve' => $advisory['cve'] ?? null,
                        'url' => $advisory['link'] ?? $advisory['url'] ?? null,
                        'affected_versions' => $advisory['affectedVersions'] ?? $advisory['affected_versions'] ?? null,
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }

    private function npmItems(string $output): array
    {
        $data = json_decode($output, true);

        if (! is_array($data)) {
            return [];
        }

        return collect($data['vulnerabilities'] ?? [])
            ->map(function (array $vulnerability, string $package): array {
                $via = collect($vulnerability['via'] ?? [])
                    ->first(fn (mixed $item): bool => is_array($item));

                return [
                    'source' => 'npm',
                    'package' => $package,
                    'severity' => strtolower((string) ($vulnerability['severity'] ?? ($via['severity'] ?? 'unknown'))),
                    'title' => (string) ($via['title'] ?? $vulnerability['title'] ?? 'Vulnerabilidade sem titulo'),
                    'cve' => null,
                    'url' => $via['url'] ?? null,
                    'affected_versions' => $vulnerability['range'] ?? null,
                    'fix_available' => $vulnerability['fixAvailable'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
