<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class MoloniService
{
    public function pdfUrl(int $documentId): ?string
    {
        $accessToken = $this->accessToken();

        if (blank($accessToken) || $documentId <= 0) {
            return null;
        }

        $payload = ['document_id' => $documentId];
        $companyId = $this->configValue('company_id');

        if (filled($companyId)) {
            $payload['company_id'] = $companyId;
        }

        $url = 'https://api.moloni.pt/v2/documents/getPDFLink/?'.http_build_query([
            'human_errors' => 'true',
            'access_token' => $accessToken,
        ]);

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->retry(2, 500, throw: false)
                ->post($url, $payload);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $url = $response->json('url');

        return filled($url) ? (string) $url : null;
    }

    private function accessToken(): ?string
    {
        $staticToken = $this->configValue('access_token');

        if (filled($staticToken)) {
            return (string) $staticToken;
        }

        $developerId = $this->configValue('developer_id');
        $clientSecret = $this->configValue('client_secret');
        $username = $this->configValue('username');
        $password = $this->configValue('password');

        if (blank($developerId) || blank($clientSecret) || blank($username) || blank($password)) {
            return null;
        }

        return Cache::remember('moloni.access_token', now()->addMinutes(45), function () use ($developerId, $clientSecret, $username, $password): ?string {
            $url = 'https://api.moloni.pt/v2/grant/?'.http_build_query([
                'grant_type' => 'password',
                'client_id' => $developerId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password,
            ]);

            try {
                $response = Http::timeout(20)
                    ->retry(2, 500, throw: false)
                    ->get($url);
            } catch (Throwable) {
                return null;
            }

            if ($response->failed()) {
                return null;
            }

            $accessToken = $response->json('access_token');

            return filled($accessToken) ? (string) $accessToken : null;
        });
    }

    private function configValue(string $key): ?string
    {
        $value = config("moloni.{$key}");

        if (filled($value)) {
            return (string) $value;
        }

        $envKey = 'MOLONI_'.strtoupper($key);
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return null;
        }

        foreach (File::lines($envPath) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, $envKey.'=')) {
                continue;
            }

            $raw = trim(substr($line, strlen($envKey) + 1));

            return trim($raw, "\"'");
        }

        return null;
    }
}
