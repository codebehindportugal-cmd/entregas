<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class SiteBackup extends Command
{
    protected $signature = 'site:backup {--no-nas : Nao sincroniza para NAS}';

    protected $description = 'Cria backup da base de dados e ficheiros principais, e opcionalmente envia para NAS.';

    public function handle(): int
    {
        $backupRoot = (string) config('operations.backup_path');
        $stamp = now()->format('Y-m-d_His');
        $target = "{$backupRoot}/{$stamp}";

        File::ensureDirectoryExists($target);

        $database = $this->backupDatabase($target);
        $manifest = [
            'created_at' => now()->toDateTimeString(),
            'app_url' => config('app.url'),
            'database' => $database,
            'files' => $this->backupFiles($target),
        ];

        File::put("{$target}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->pruneOldBackups($backupRoot);

        if (! $this->option('no-nas')) {
            $this->syncToNas($backupRoot);
        }

        $this->info("Backup criado em {$target}");

        return self::SUCCESS;
    }

    private function backupDatabase(string $target): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) === 'sqlite') {
            $database = (string) ($config['database'] ?? '');

            if ($database !== '' && File::exists($database)) {
                File::copy($database, "{$target}/database.sqlite");

                return ['driver' => 'sqlite', 'path' => "{$target}/database.sqlite"];
            }
        }

        if (($config['driver'] ?? null) === 'mysql') {
            $path = "{$target}/database.sql";
            $command = [
                'mysqldump',
                '--single-transaction',
                '--quick',
                '-h'.$config['host'],
                '-P'.(string) $config['port'],
                '-u'.$config['username'],
                '--result-file='.$path,
                (string) $config['database'],
            ];

            $process = new Process($command, base_path(), [
                'MYSQL_PWD' => (string) ($config['password'] ?? ''),
            ]);
            $process->setTimeout(600);
            $process->run();

            return [
                'driver' => 'mysql',
                'path' => $path,
                'exit_code' => $process->getExitCode(),
                'error' => trim($process->getErrorOutput()),
            ];
        }

        return ['driver' => $config['driver'] ?? 'unknown', 'error' => 'Driver de backup nao suportado.'];
    }

    private function backupFiles(string $target): array
    {
        $files = ['.env', 'composer.lock', 'package-lock.json'];
        $directories = ['storage/app/public', 'public/build'];
        $copied = [];

        foreach ($files as $file) {
            if (File::exists(base_path($file))) {
                File::copy(base_path($file), "{$target}/".basename($file));
                $copied[] = $file;
            }
        }

        foreach ($directories as $directory) {
            if (File::isDirectory(base_path($directory))) {
                File::copyDirectory(base_path($directory), "{$target}/{$directory}");
                $copied[] = $directory;
            }
        }

        return $copied;
    }

    private function pruneOldBackups(string $backupRoot): void
    {
        $keepDays = (int) config('operations.backup_keep_days', 14);
        $threshold = now()->subDays($keepDays)->getTimestamp();

        foreach (File::directories($backupRoot) as $directory) {
            if (File::lastModified($directory) < $threshold) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function syncToNas(string $backupRoot): void
    {
        $target = config('operations.nas_rsync_target');

        if (blank($target)) {
            $this->warn('OPERATIONS_NAS_RSYNC_TARGET nao configurado; backup ficou apenas local.');

            return;
        }

        $source = rtrim($backupRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $process = new Process(['rsync', '-az', '--delete', $source, (string) $target], base_path());
        $process->setTimeout(900);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Falha ao sincronizar backup para NAS: '.trim($process->getErrorOutput()));
        }
    }
}
