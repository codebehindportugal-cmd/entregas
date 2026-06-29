<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(): View
    {
        return view('operations.index', [
            'health' => $this->jsonFile(config('operations.health_scan_path')),
            'security' => $this->jsonFile(config('operations.security_scan_path')),
            'updates' => $this->jsonFile(config('operations.updates_scan_path')),
            'backups' => collect(File::directories((string) config('operations.backup_path')))
                ->map(fn (string $path): array => [
                    'name' => basename($path),
                    'modified_at' => date('Y-m-d H:i:s', File::lastModified($path)),
                    'manifest' => $this->jsonFile($path.'/manifest.json'),
                ])
                ->sortByDesc('name')
                ->take(10)
                ->values(),
        ]);
    }

    public function health(): RedirectResponse
    {
        Artisan::call('site:health-check');

        return back()->with('status', 'Healthcheck executado.');
    }

    public function security(): RedirectResponse
    {
        Artisan::call('site:security-scan');

        return back()->with('status', 'Scan de seguranca executado.');
    }

    public function updates(): RedirectResponse
    {
        Artisan::call('site:update-check');

        return back()->with('status', 'Verificacao de updates executada.');
    }

    public function backup(): RedirectResponse
    {
        Artisan::call('site:backup');

        return back()->with('status', 'Backup executado.');
    }

    private function jsonFile(mixed $path): ?array
    {
        $path = (string) $path;

        if ($path === '' || ! File::exists($path)) {
            return null;
        }

        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? $data : null;
    }
}
