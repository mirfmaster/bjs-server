<?php

namespace App\Client;

use App\Exceptions\RateLimitException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramClient
{
    private static array $proxyRotationErrors = [
        // NOTE: SHOULD BE LOWERCASE
        'connection timeout',
        'connection reset by peer',
        'proxy connect aborted',
        'failed to connect',
        'operation timed out',
        'proxy rotation needed',
        'please wait a few minutes',
        'rate limited',
        'unknown error',
    ];

    private ProxyManager $proxyManager;

    private ?array $currentProxy = null;

    private string $preferredProxyType;

    public function __construct(?string $forceProxyType = null)
    {
        $this->proxyManager = new ProxyManager($forceProxyType);
        $this->preferredProxyType = $forceProxyType ?? Config::get('app.preferred_proxy');
    }

    public function fetchProfile(string $username, int $maxRetries = 4)
    {
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $this->ensureProxyAvailable();
                if (config('app.debug')) {
                    dump("Using proxy {$this->currentProxy['type']}: {$this->currentProxy['label']}");
                }
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
        // When using forced proxy type, only try that type
        $proxy = $this->proxyManager->getAvailableProxy($this->preferredProxyType);

        if (! $proxy && $this->preferredProxyType !== ProxyManager::PYPROXY) {
            // Only try fallback if proxy type isn't forced
            Log::info('Primary proxy unavailable, trying PyProxy fallback');
            $proxy = $this->proxyManager->getAvailableProxy(ProxyManager::PYPROXY);
        }

        return $proxy;
    }

    public function forceRandomProxy(): void
    {
        $this->currentProxy = null;
        $this->getNextProxy();
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
        $body = $response->json();

        if (! $response->successful()) {
            if ($response->status() === 404) {
                return (object) ['username' => $username, 'found' => false];
            }
            throw new \Exception('Failed to fetch profile: '.$response->body());
        }

        if (isset($body['status']) && $body['status'] === 'fail') {
            throw new RateLimitException($body['message'] ?? 'Unknown error');
        }

        if (
            ! isset($body['data']) ||
            ! isset($body['data']['user']) ||
            $body['data']['user'] === null
        ) {
            return (object) ['username' => $username, 'found' => false];
        }

        $user = $body['data']['user'];

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

    private function shouldRotateProxy(\Exception $e): bool
    {
        if ($e instanceof RateLimitException) {
            return true;
        }

        if ($e instanceof ConnectionException || $e instanceof RequestException) {
            $message = strtolower($e->getMessage());
            foreach (self::$proxyRotationErrors as $errorPhrase) {
                if (str_contains($message, strtolower($errorPhrase))) {
                    return true;
                }
            }
        }

        $fullMessage = strtolower($e->getMessage());
        if (
            str_contains($fullMessage, 'please wait a few minutes') ||
            str_contains($fullMessage, 'require_login') ||
            str_contains($fullMessage, 'status":"fail"')
        ) {
            return true;
        }

        return false;
    }

    private function handleRequestError(\Exception $e, int $attempt, int $maxRetries): bool
    {
        $shouldRotate = $this->shouldRotateProxy($e);

        Log::warning('Instagram request failed', [
            'attempt' => $attempt,
            'error' => $e->getMessage(),
            'proxy_type' => $this->currentProxy ? $this->currentProxy['type'] : null,
            'proxy_label' => $this->currentProxy ? $this->currentProxy['label'] : null,
            'preferred_type' => $this->preferredProxyType,
            'should_rotate' => $shouldRotate,
        ]);

        if ($attempt === $maxRetries) {
            return false;
        }

        if ($shouldRotate) {
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
            sleep(3);
        }

        $this->currentProxy = $this->getNextProxy();
    }
}
