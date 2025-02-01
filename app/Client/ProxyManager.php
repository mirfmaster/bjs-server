<?php

namespace App\Client;

use App\Traits\LoggerTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ProxyManager
{
    use LoggerTrait;

    private string $managedProxyUrl;

    private string $managedProxyApiKey;

    private string $pyProxyUsername;

    private string $pyProxyPassword;

    public function __construct()
    {
        $this->managedProxyUrl = config('app.proxy_url');
        $this->managedProxyApiKey = config('app.proxy_api_key');
        $this->pyProxyUsername = config('app.pyproxy_username');
        $this->pyProxyPassword = config('app.pyproxy_password');
    }

    public function getAvailableProxy(): ?array
    {
        // Try managed proxies first
        $managedProxies = $this->getActiveProxies();

        if ($managedProxies->isNotEmpty()) {
            $proxy = $managedProxies->random();

            return [
                'type' => 'managed',
                'label' => $proxy['proxy_label'],
                'connection_string' => $this->formatProxyString($proxy),
            ];
        }

        // Fallback to PyProxy if no managed proxies available
        return [
            'type' => 'pyproxy',
            'label' => null, // PyProxy doesn't need a label for rotation
            'connection_string' => $this->generatePyProxyUrl(),
        ];
    }

    public function rotateProxy(?string $label, string $type = 'managed'): bool
    {
        if ($type === 'managed' && $label) {
            return $this->rotateIp($label);
        }

        if ($type === 'pyproxy') {
            // For PyProxy, we don't need to call any rotation API
            // A new session will be created with the next generatePyProxyUrl call
            return true;
        }

        return false;
    }

    public function getActiveProxies(): Collection
    {
        try {
            $response = Http::get($this->buildUrl('/api/get-all-proxy'));

            if (! $response->successful()) {
                return collect([]);
            }

            $proxies = collect($response->json()['data'] ?? []);

            return $proxies->filter(function ($proxy) {
                return ($proxy['status'] ?? false) && ! ($proxy['is_rotating'] ?? false);
            })->values();
        } catch (\Throwable $th) {
            $this->logError($th);

            return collect([]);
        }
    }

    private function rotateIp(string $label): bool
    {
        try {
            $response = Http::get($this->buildUrl('/api/rotate-ip'), [
                'proxy_label' => $label,
            ]);

            return $response->successful() && ($response->json()['status'] ?? false);
        } catch (\Throwable $th) {
            $this->logError($th, ['label' => $label]);

            return false;
        }
    }

    private function generatePyProxyUrl(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $random_string = substr(str_shuffle($characters), 0, $length);

        return sprintf(
            'http://%s-zone-resi-region-id-st-jakarta-city-jakarta-session-%s-sessTime-10:%s@d169f2e23873ee25.tuf.as.pyproxy.io:16666',
            $this->pyProxyUsername,
            $random_string,
            $this->pyProxyPassword
        );
    }

    private function buildUrl(string $endpoint): string
    {
        return $this->managedProxyUrl . $endpoint . '?api_key=' . $this->managedProxyApiKey;
    }

    private function formatProxyString(array $proxyData): string
    {
        return sprintf(
            '%s://%s:%s@%s:%s',
            $proxyData['proxy_type'] ?? 'http',
            $proxyData['proxy_username'] ?? '',
            $proxyData['proxy_password'] ?? '',
            $proxyData['proxy_ip'] ?? '',
            $proxyData['proxy_port'] ?? ''
        );
    }
}

