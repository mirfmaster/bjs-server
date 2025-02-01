<?php

namespace App\Client;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramClient
{
    private static array $proxyRotationErrors = [
        'Connection timeout',
        'Connection reset by peer',
        'Proxy CONNECT aborted',
        'Failed to connect',
        'Operation timed out',
        'proxy rotation needed',
        'Please wait a few minutes',
        'Rate limited',
    ];

    private ProxyManager $proxyManager;

    private ?array $currentProxy = null;

    private string $preferredProxyType;

    public function __construct()
    {
        $this->proxyManager = new ProxyManager;
        $this->preferredProxyType = Config::get('app.preferred_proxy');
    }

    public function fetchProfile(string $username, int $maxRetries = 4)
    {
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $this->ensureProxyAvailable();
                $response = $this->makeProfileRequest($username);

                return $this->processProfileResponse($response, $username);
            } catch (\Exception $e) {
                if (! $this->handleRequestError($e, $attempt, $maxRetries)) {
                    throw $e;
                }
                $attempt++;
            }
        }

        throw new \Exception('Max retries exceeded');
    }

    private function ensureProxyAvailable(): void
    {
        if (! $this->currentProxy) {
            $this->currentProxy = $this->getNextProxy();

            if (! $this->currentProxy) {
                throw new \Exception('No proxies available');
            }
        }
    }

    private function getNextProxy(): ?array
    {
        // Try preferred proxy type first
        $proxy = $this->proxyManager->getAvailableProxy($this->preferredProxyType);

        if (! $proxy) {
            // Try fallback proxy type
            $fallbackType = ProxyManager::PYPROXY;

            $proxy = $this->proxyManager->getAvailableProxy($fallbackType);
        }

        return $proxy;
    }

    private function makeProfileRequest(string $username)
    {
        return Http::withHeaders([
            'X-IG-App-ID' => '936619743392459',
            'Origin' => 'https://www.instagram.com',
            'Referer' => 'https://www.instagram.com',
            'X-ASBD-ID' => '129477',
        ])
            ->timeout(5)
            ->connectTimeout(3)
            ->withOptions(['proxy' => $this->currentProxy['connection_string']])
            ->get('https://www.instagram.com/api/v1/users/web_profile_info/', [
                'username' => $username,
            ]);
    }

    private function processProfileResponse($response, string $username)
    {
        if ($response->status() === 404) {
            return (object) ['username' => $username, 'found' => false];
        }

        if ($response->successful() && ! empty($response['data']['user'])) {
            $user = $response['data']['user'];

            return (object) [
                'username' => $username,
                'found' => true,
                'pk' => $user['id'],
                'is_private' => $user['is_private'],
                'has_anonymous_profile_picture' => strpos($user['profile_pic_url'], '2446069589734326272') !== false,
                'total_media_timeline' => intval($user['edge_owner_to_timeline_media']['count']),
                'follower_count' => $user['edge_followed_by']['count'],
                'following_count' => $user['edge_follow']['count'],
            ];
        }

        throw new \Exception('Failed to fetch profile: '.$response->body());
    }

    private function handleRequestError(\Exception $e, int $attempt, int $maxRetries): bool
    {
        Log::warning('Instagram request failed', [
            'attempt' => $attempt,
            'error' => $e->getMessage(),
            'proxy_type' => $this->currentProxy ? $this->currentProxy['type'] : null,
            'proxy_label' => $this->currentProxy ? $this->currentProxy['label'] : null,
            'preferred_type' => $this->preferredProxyType,
        ]);

        if ($attempt === $maxRetries) {
            return false;
        }

        if ($this->shouldRotateProxy($e)) {
            $this->rotateProxy();
        }

        // Exponential backoff with max delay of 30 seconds
        $delay = min(pow(2, $attempt) * 1000, 30000);
        usleep($delay * 1000);

        return true;
    }

    private function rotateProxy(): void
    {
        if ($this->currentProxy) {
            $this->proxyManager->rotateProxy(
                $this->currentProxy['label'],
                $this->currentProxy['type']
            );
        }

        $this->currentProxy = $this->getNextProxy();
    }

    private function shouldRotateProxy(\Exception $e): bool
    {
        if (! ($e instanceof ConnectionException || $e instanceof RequestException)) {
            return false;
        }

        $message = strtolower($e->getMessage());
        foreach (self::$proxyRotationErrors as $errorPhrase) {
            if (stripos($message, strtolower($errorPhrase)) !== false) {
                return true;
            }
        }

        return false;
    }
}
