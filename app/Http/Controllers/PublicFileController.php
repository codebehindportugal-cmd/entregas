<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicFileController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        abort_unless($request->user(), 403);

        $path = str_replace('\\', '/', $path);
        abort_if($path === '' || str_contains($path, '..') || str_starts_with($path, '/'), 403);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function aiJob(Request $request, AiJob $job): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(Storage::disk('public')->exists($job->image_path), 404);

        return response()->file(Storage::disk('public')->path($job->image_path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
