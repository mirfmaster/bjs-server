<?php

namespace App\Client;

use App\Traits\LoggerTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyManager
{
    use LoggerTrait;

    private $baseUrl;

    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('app.proxy_url');
        $this->apiKey = config('app.proxy_api_key');
    }

    /**
     * Get list of all available proxies
     *
     * @return array Array of proxy details
     */
    public function getAllProxies(): array
    {
        try {
            $response = Http::get($this->buildUrl('/api/get-all-proxy'));

            if (! $response->successful()) {
                Log::error('Failed to get proxies', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $data = $response->json();

            return $data['data'] ?? [];
        } catch (\Throwable $th) {
            $this->logError($th);

            return [];
        }
    }

    /**
     * Get proxy details by label
     *
     * @param  string  $label  Proxy label/identifier
     * @return array|null Proxy details or null if not found
     */
    public function getProxyByLabel(string $label): ?array
    {
        try {
            $response = Http::get($this->buildUrl('/api/get-by-label-proxy'), [
                'proxy_label' => $label,
            ]);

            if (! $response->successful()) {
                Log::error('Failed to get proxy by label', [
                    'label' => $label,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return $data['data'] ?? null;
        } catch (\Throwable $th) {
            $this->logError($th, ['label' => $label]);

            return null;
        }
    }

    /**
     * Rotate IP for specific proxy
     *
     * @param  string  $label  Proxy label to rotate
     * @return bool Whether rotation was successful
     */
    public function rotateIp(string $label): bool
    {
        try {
            $response = Http::get($this->buildUrl('/api/rotate-ip'), [
                'proxy_label' => $label,
            ]);

            if (! $response->successful()) {
                Log::error('Failed to rotate IP', [
                    'label' => $label,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json();

            return $data['status'] ?? false;
        } catch (\Throwable $th) {
            $this->logError($th, ['label' => $label]);

            return false;
        }
    }

    /**
     * Rotate IPs for all proxies
     *
     * @return array Result of rotation containing success count and failed count
     */
    public function rotateAllIps(): array
    {
        try {
            $response = Http::get($this->buildUrl('/api/rotate-ip-all-proxy'));

            if (! $response->successful()) {
                Log::error('Failed to rotate all IPs', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success_rotate' => 0,
                    'failed_rotate' => 0,
                ];
            }

            $data = $response->json();

            return [
                'success_rotate' => $data['success_rotate'] ?? 0,
                'failed_rotate' => $data['failed_rotate'] ?? 0,
            ];
        } catch (\Throwable $th) {
            $this->logError($th);

            return [
                'success_rotate' => 0,
                'failed_rotate' => 0,
            ];
        }
    }

    /**
     * Get list of active proxies with their status
     * Always fetches fresh data from API
     *
     * @return Collection Collection of active proxies
     */
    public function getActiveProxies(): Collection
    {
        try {
            $allProxies = collect($this->getAllProxies());

            // Filter for active proxies
            return $allProxies->filter(function ($proxy) {
                return ($proxy['status'] ?? false) && ! ($proxy['is_rotating'] ?? false);
            })->values();

        } catch (\Throwable $th) {
            $this->logError($th);

            return collect([]);
        }
    }

    /**
     * Get status of all proxies
     * Always fetches fresh data from API
     *
     * @return array Array of proxy labels and their active status
     */
    public function getProxyStatus(): array
    {
        try {
            $allProxies = $this->getAllProxies();

            return collect($allProxies)->mapWithKeys(function ($proxy) {
                $label = $proxy['proxy_label'];

                return [
                    $label => ($proxy['status'] ?? false) && ! ($proxy['is_rotating'] ?? false),
                ];
            })->toArray();

        } catch (\Throwable $th) {
            $this->logError($th);

            return [];
        }
    }

    /**
     * Check if a specific proxy is active and available
     * Always fetches fresh data from API
     *
     * @param  string  $label  Proxy label to check
     * @return bool Whether proxy is active and available
     */
    public function isProxyActive(string $label): bool
    {
        $proxy = $this->getProxyByLabel($label);
        if (! $proxy) {
            return false;
        }

        return ($proxy['status'] ?? false) && ! ($proxy['is_rotating'] ?? false);
    }

    /**
     * Build URL with API key
     */
    private function buildUrl(string $endpoint): string
    {
        return $this->baseUrl.$endpoint.'?api_key='.$this->apiKey;
    }

    /**
     * Format proxy details into connection string
     * sample: http://user:pass@host:port
     *
     * @param  array  $proxyData  Proxy details from API
     * @return string Formatted proxy connection string
     */
    public function formatProxyString(array $proxyData): string
    {
        $type = $proxyData['proxy_type'] ?? 'http';
        $username = $proxyData['proxy_username'] ?? '';
        $password = $proxyData['proxy_password'] ?? '';
        $ip = $proxyData['proxy_ip'] ?? '';
        $port = $proxyData['proxy_port'] ?? '';

        return sprintf(
            '%s://%s:%s@%s:%s',
            $type,
            $username,
            $password,
            $ip,
            $port
        );
    }
}
