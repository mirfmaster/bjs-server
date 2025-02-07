<?php

namespace App\Client;

use App\Traits\LoggerTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ProxyManager
{
    use LoggerTrait;

    public const PYPROXY = 'pyproxy';

    public const MANAGEDPROXY = 'managed';

    private string $managedProxyUrl;

    private string $managedProxyApiKey;

    private string $pyProxyUsername;

    private string $pyProxyPassword;

    private ?string $forceProxyType;

    public function __construct(?string $forceProxyType = null)
    {
        $this->managedProxyUrl = config('app.proxy_url');
        $this->managedProxyApiKey = config('app.proxy_api_key');
        $this->pyProxyUsername = config('app.pyproxy_username');
        $this->pyProxyPassword = config('app.pyproxy_password');
        $this->forceProxyType = $forceProxyType;
    }

    /**
     * Get a specific managed proxy by its label
     */
    public function getManagedProxy(string $label): ?array
    {
        if ($this->forceProxyType === self::PYPROXY) {
            return null;
        }

        try {
            $response = Http::get($this->buildUrl('/api/get-by-label-proxy'), [
                'proxy_label' => $label,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $proxy = $response->json()['data'] ?? null;
            if (! $proxy || ! ($proxy['status'] ?? false) || ($proxy['is_rotating'] ?? false)) {
                return null;
            }

            return [
                'type' => self::MANAGEDPROXY,
                'label' => $proxy['proxy_label'],
                'connection_string' => $this->formatProxyString($proxy),
            ];
        } catch (\Throwable $th) {
            $this->logError($th);

            return null;
        }
    }

    /**
     * Get a specific PyProxy with a custom session ID
     */
    public function getPyProxy(?string $sessionId = null): ?array
    {
        if ($this->forceProxyType === self::MANAGEDPROXY) {
            return null;
        }

        return [
            'type' => self::PYPROXY,
            'label' => $sessionId,
            'connection_string' => $this->generatePyProxyUrl($sessionId),
        ];
    }

    /**
     * Get any available proxy with optional preference
     */
    public function getAvailableProxy(?string $preferredType = null): ?array
    {
        // If force proxy type is set, it overrides the preferred type
        $effectiveType = $this->forceProxyType ?? $preferredType;

        if ($effectiveType === self::PYPROXY) {
            return $this->getPyProxy();
        }

        if ($effectiveType === self::MANAGEDPROXY) {
            $managedProxies = $this->getActiveProxies();
            if ($managedProxies->isNotEmpty()) {
                $proxy = $managedProxies->random();

                return [
                    'type' => self::MANAGEDPROXY,
                    'label' => $proxy['proxy_label'],
                    'connection_string' => $this->formatProxyString($proxy),
                ];
            }
            // If force proxy type is managed, return null instead of falling back
            if ($this->forceProxyType === self::MANAGEDPROXY) {
                return null;
            }
        }

        // No force type or preference - try managed first, then fallback to PyProxy
        $managedProxies = $this->getActiveProxies();
        if ($managedProxies->isNotEmpty()) {
            $proxy = $managedProxies->random();

            return [
                'type' => self::MANAGEDPROXY,
                'label' => $proxy['proxy_label'],
                'connection_string' => $this->formatProxyString($proxy),
            ];
        }

        return $this->getPyProxy();
    }

    public function rotateProxy(?string $label, string $type = self::MANAGEDPROXY): bool
    {
        // Don't allow rotation if it conflicts with forced proxy type
        if ($this->forceProxyType && $type !== $this->forceProxyType) {
            return false;
        }

        if ($type === self::MANAGEDPROXY && $label) {
            return $this->rotateIp($label);
        }

        if ($type === self::PYPROXY) {
            return true;
        }

        return false;
    }

    public function getActiveProxies(): Collection
    {
        if ($this->forceProxyType === self::PYPROXY) {
            return collect([]);
        }

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

    private function generatePyProxyUrl(?string $sessionId = null): string
    {
        if (! $sessionId) {
            $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $sessionId = substr(str_shuffle($characters), 0, 12);
        }

        return sprintf(
            'http://%s-zone-resi-region-id-st-jakarta-city-jakarta-session-%s-sessTime-10:%s@d169f2e23873ee25.tuf.as.pyproxy.io:16666',
            $this->pyProxyUsername,
            $sessionId,
            $this->pyProxyPassword
        );
    }

    private function buildUrl(string $endpoint): string
    {
        return $this->managedProxyUrl.$endpoint.'?api_key='.$this->managedProxyApiKey;
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
