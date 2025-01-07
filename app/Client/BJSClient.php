<?php

namespace App\Client;

use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class BJSClient
{
    use LoggerTrait;

    private const AUTH_CACHE_KEY = 'system:bjs:auth';

    private const AUTH_CACHE_TTL = 86400; // 24 hours

    public Client $cli;

    public Client $cliXML;

    public Http $http;

    private RedisCookieJar $cookieJar;

    private string $baseUrl;

    private ?string $bearerToken = null;

    public function __construct()
    {
        $this->baseUrl = config('app.bjs_api');

        // Initialize Redis-based cookie jar
        $this->cookieJar = new RedisCookieJar;

        // Load saved auth state
        $this->loadAuthState();

        // Initialize HTTP clients
        $this->initializeClients();
    }

    private function loadAuthState(): void
    {
        $authData = Cache::get(self::AUTH_CACHE_KEY);
        if ($authData) {
            $this->bearerToken = $authData['token'];
            Log::debug('Loaded auth state from cache', ['has_token' => ! empty($this->bearerToken)]);
        }
    }

    private function saveAuthState(): void
    {
        $authData = [
            'token' => $this->bearerToken,
            'updated_at' => now()->timestamp,
        ];
        Cache::put(self::AUTH_CACHE_KEY, $authData, self::AUTH_CACHE_TTL);
        Log::debug('Saved auth state to cache', ['has_token' => ! empty($this->bearerToken)]);
    }

    private function clearAuthState(): void
    {
        $this->bearerToken = null;
        Cache::forget(self::AUTH_CACHE_KEY);
        $this->cookieJar->clear();
        Log::debug('Cleared auth state');
    }

    private function initializeClients(): void
    {
        $defaultHeaders = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => 'https://belanjasosmed.com/admin/orders?status=0&service=11',
            'Origin' => $this->baseUrl,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'en-US',
            'Sec-Ch-Ua' => '"Chromium";v="128", "Not:A-Brand";v="24", "Google Chrome";v="128"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Linux"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Connection' => 'keep-alive',
        ];

        // Add bearer token if available
        if ($this->bearerToken) {
            $defaultHeaders['Authorization'] = 'Bearer '.$this->bearerToken;
        }

        $this->cli = new Client([
            'cookies' => $this->cookieJar,
            'base_uri' => $this->baseUrl,
            'headers' => $defaultHeaders,
        ]);

        $this->cliXML = new Client([
            'cookies' => $this->cookieJar,
            'base_uri' => $this->baseUrl,
            'headers' => array_merge($defaultHeaders, [
                'Content-Type' => 'application/json',
            ]),
        ]);
    }

    public function loginWithToken(): bool
    {
        try {
            $response = $this->cli->request('POST', '/admin/api/auth', [
                'json' => [
                    'login' => config('app.bjs_username'),
                    'password' => config('app.bjs_password'),
                    're_captcha' => '',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $result = json_decode((string) $response->getBody(), true);

            if (! $result['success'] || empty($result['data']['access_token'])) {
                return false;
            }

            // Store the token and reinitialize clients with new token
            $this->bearerToken = $result['data']['access_token'];
            $this->saveAuthState();
            $this->cookieJar->persist();
            $this->initializeClients();

            return true;
        } catch (\Throwable $th) {
            $this->logError($th);
            $this->clearAuthState();

            return false;
        }
    }

    public function login(): bool
    {
        try {
            $csrf = $this->getCSRFToken();
            if ($csrf === null) {
                return false;
            }

            $response = $this->cli->request('POST', '/admin', [
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => 'https://belanjasosmed.com/admin',
                    'Origin' => 'https://belanjasosmed.com/',
                ],
                'form_params' => [
                    '_csrf_admin' => $csrf,
                    'SignInForm[login]' => config('app.bjs_username'),
                    'SignInForm[password]' => config('app.bjs_password'),
                    'SignInForm[remember]' => 1,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $this->cookieJar->persist();
                $this->initializeClients();

                return true;
            }

            return false;
        } catch (\Throwable $th) {
            $this->logError($th);
            $this->clearAuthState();

            return false;
        }
    }

    public function checkAuth(): bool
    {
        try {
            $response = $this->cliXML->request('GET', '/admin/api/general/check-auth');
            $result = json_decode((string) $response->getBody());

            if (! $result?->data?->auth) {
                $this->clearAuthState();

                return false;
            }

            return true;
        } catch (\Throwable $th) {
            $this->clearAuthState();
            $this->logError($th);

            return false;
        }
    }

    private function getCSRFToken(): ?string
    {
        try {
            $response = $this->cli->request('GET', '/admin');
            $html = (string) $response->getBody();

            return $this->extractToken($html);
        } catch (\Throwable $th) {
            $this->logError($th);

            return null;
        }
    }

    private function extractToken(string $html): ?string
    {
        if (preg_match('/<meta name="csrf-token" content="(.*?)">/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getInfo(int $orderId): object
    {
        try {
            $response = $this->cliXML->get("/admin/api/orders/get-info/$orderId?type=2&num=0");

            return json_decode((string) $response->getBody());
        } catch (\Throwable $th) {
            $this->logError($th);

            return (object) [];
        }
    }

    public function setStartCount(int $id, int $start): bool
    {
        $ctx = [
            'form_params' => ['start_count' => $start],
        ];
        try {
            $response = $this->cliXML->post("/admin/api/orders/set-start-count/$id", $ctx);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function setRemains(int $id, int $remains): bool
    {
        $ctx = [
            'form_params' => ['remains' => $remains],
        ];
        try {
            $response = $this->cliXML->post("/admin/api/orders/set-remains/$id", $ctx);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $th) {
            $this->logError($th, $ctx);

            return false;
        }
    }

    public function setPartial(int $id, int $remains): bool
    {
        $ctx = [
            'form_params' => ['remains' => $remains],
        ];
        try {
            $response = $this->cliXML->post("/admin/api/orders/set-partial/$id", $ctx);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $th) {
            $this->logError($th, $ctx);

            return false;
        }
    }

    public function cancelOrder(int $id): bool
    {
        try {
            $response = $this->cliXML->post("/admin/api/orders/cancel/$id");

            return $response->getStatusCode() === 200;
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function changeStatus(int $id, string $status): bool
    {
        $ctx = [
            'form_params' => ['status' => $status],
        ];
        try {
            $response = $this->cliXML->post("/admin/api/orders/change-status/$id", $ctx);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function changeStatusServices(int $id, bool $status): bool
    {
        $ctx = [
            'form_params' => ['status' => $status],
        ];
        try {
            $response = $this->cliXML->post("/admin/api/services/change-status/$id", $ctx);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function getUsername(string $link): string
    {
        $input = str_replace('@', '', $link);
        $input = str_replace(['/reel/', '/tv/'], '/p/', $input);

        if (! filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        $path = parse_url($input, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        return $pathParts[0];
    }

    public function getOrdersList(int $status, int $serviceId, int $pageSize): ResponseInterface
    {
        return $this->cliXML->get("/admin/api/orders/list?status=$status&service=$serviceId&page_size=$pageSize");
    }

    public function getOrdersData(int $serviceId, int $status, int $pageSize = 100): Collection
    {
        try {
            $response = $this->getOrdersList($status, $serviceId, $pageSize);
            $data = json_decode((string) $response->getBody());

            return collect($data->data->orders);
        } catch (\Throwable $th) {
            $this->logError($th);

            return collect([]);
        }
    }
}
