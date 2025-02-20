<?php

namespace App\Helpers;

use App\Client\ProxyManager;
use App\Services\TempMailService;
use Illuminate\Support\Facades\Http;

class InstagramHelper
{
    protected $tempMailDomains = [
        'gonetor.com',
        'zlorkun.com',
        'somelora.com',
        'vvatxiy.com',
        'dygovil.com',
        'tidissajiiu.com',
        'vafyxh.com',
        'knmcadibav.com',
        'smykwb.com',
        'wywnxa.com',
    ];

    protected $userAgents = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        'Mozilla/5.0 (iPad; CPU OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_1_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/16D57',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 13_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.4 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko)',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 13_1_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 9_3_5 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13G36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.5 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15G77',
        'Mozilla/5.0 (iPad; CPU OS 9_3_5 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13G36 Safari/601.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_3 like Mac OS X) AppleWebKit/603.3.8 (KHTML, like Gecko) Version/10.0 Mobile/14G60 Safari/602.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 13_1_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_1_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/16C101',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Mobile/15E148 Safari/604.1',
    ];

    protected $baseUrl = 'https://www.instagram.com/api/v1';

    public ProxyManager $proxyManager;

    public TempMailService $tempMailService;

    protected ?array $currentProxy = null;

    public function __construct()
    {
        $this->proxyManager = new ProxyManager(ProxyManager::PYPROXY);
        $this->tempMailService = new TempMailService();
    }

    protected function ensureProxyAvailable(): void
    {
        if (! $this->currentProxy) {
            $this->currentProxy = $this->proxyManager->getAvailableProxy();
            if (! $this->currentProxy) {
                throw new \Exception('No proxies available');
            }
        }
    }

    protected function handleRequestError(\Exception $e, int $attempt, int $maxRetries): bool
    {
        // If proxy-related error, rotate proxy
        if (
            str_contains(strtolower($e->getMessage()), 'proxy') ||
            str_contains(strtolower($e->getMessage()), 'timeout') ||
            str_contains(strtolower($e->getMessage()), 'connection')
        ) {
            $this->rotateProxy();
        }

        if ($attempt === $maxRetries) {
            return false;
        }

        // Exponential backoff
        $delay = min(pow(2, $attempt) * 1000, 5000); // Max 5 seconds
        usleep($delay * 1000);

        return true;
    }

    protected function rotateProxy(): void
    {
        if ($this->currentProxy) {
            $this->proxyManager->rotateProxy(
                $this->currentProxy['label'],
                $this->currentProxy['type']
            );
            sleep(2);
        }
        $this->currentProxy = $this->proxyManager->getAvailableProxy();
    }

    protected function makeRequest($method, $url, $options = [])
    {
        $maxRetries = 3;
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $this->ensureProxyAvailable();

                $requestOptions = array_merge($options, [
                    'proxy' => $this->currentProxy['connection_string'],
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ]);

                $response = Http::withOptions($requestOptions)
                    ->withHeaders($options['headers'] ?? []);

                if ($method === 'GET') {
                    $response = $response->get($url, $options['query'] ?? []);
                } else {
                    $response = $response->post($url, $options['data'] ?? []);
                }

                return $response;
            } catch (\Exception $e) {
                if (! $this->handleRequestError($e, $attempt, $maxRetries)) {
                    throw $e;
                }
                $attempt++;
            }
        }

        throw new \Exception('Max retries exceeded');
    }

    public function generateUUID($keepDashes = true)
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );

        return $keepDashes ? $uuid : str_replace('-', '', $uuid);
    }

    public function generateDeviceId()
    {
        $volatileSeed = now()->timestamp;
        $uuid = $this->generateUUID(false);

        return 'android-'.substr(md5($uuid.$volatileSeed), 16);
    }

    public function generateUserAgent()
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    public function getRandomTempMailDomain()
    {
        return $this->tempMailDomains[array_rand($this->tempMailDomains)];
    }

    public function generateRandomName()
    {
        // Simple name generation logic - can be expanded
        $response = Http::get('http://ninjaname.horseridersupply.com/indonesian_name.php', [
            'number_generate' => '30',
            'gender_type' => 'female',
        ]);

        if ($response->successful()) {
            preg_match_all('~(&bull; (.*?)<br/>&bull; )~', $response->body(), $matches);
            if (isset($matches[2]) && ! empty($matches[2])) {
                return $matches[2][array_rand($matches[2])];
            }
        }

        throw new \Exception('not user');
    }

    public function getInitialCookies($userAgent): ?array
    {
        $deviceId = $this->generateDeviceId();

        try {
            $response = $this->makeRequest('GET', 'https://i.instagram.com/api/v1/web/accounts/login/ajax/', [
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                ],
            ]);

            $headers = $response->headers();
            $cookies = $this->parseCookiesFromHeaders($headers);
            $cookies['device_id'] = $deviceId;

            return $cookies;
        } catch (\Exception $e) {
            \dump('Failed to get initial cookies: '.$e->getMessage());

            return null;
        }
    }

    protected function parseCookiesFromHeaders(array $headers): array
    {
        $cookies = [];

        // Get all Set-Cookie headers
        $setCookieHeaders = $headers['Set-Cookie'] ?? [];
        if (! is_array($setCookieHeaders)) {
            $setCookieHeaders = [$setCookieHeaders];
        }

        foreach ($setCookieHeaders as $cookieString) {
            if (preg_match('/^([^=]+)=([^;]+)/', $cookieString, $matches)) {
                $cookies[$matches[1]] = $matches[2];
            }
        }

        return $cookies;
    }

    public function formatCookies(array $cookies): string
    {
        return collect($cookies)
            ->except('device_id')
            ->map(fn ($value, $key) => "$key=$value")
            ->implode('; ');
    }

    private function generateUsernameVariations(string $baseName): array
    {
        $variations = [];

        // Basic variations
        $variations[] = $baseName;
        $variations[] = strtolower($baseName);

        // Add underscores
        $variations[] = str_replace(' ', '_', $baseName);

        // Add dots
        $variations[] = str_replace(' ', '.', $baseName);

        // Mix of dots and underscores
        $variations[] = str_replace(' ', '._', $baseName);

        // Add numbers at end
        for ($i = 0; $i < 5; $i++) {
            $randNum = rand(100, 999);
            $variations[] = $baseName.$randNum;
            $variations[] = $baseName.'_'.$randNum;
            $variations[] = $baseName.'.'.$randNum;
        }

        // Add year variations
        $years = [date('y'), date('Y'), rand(90, 99)];
        foreach ($years as $year) {
            $variations[] = $baseName.$year;
            $variations[] = $baseName.'_'.$year;
            $variations[] = $baseName.'.'.$year;
        }

        // Add prefix variations
        $prefixes = ['the', 'real', 'its', 'im'];
        foreach ($prefixes as $prefix) {
            $variations[] = $prefix.$baseName;
            $variations[] = $prefix.'_'.$baseName;
            $variations[] = $prefix.'.'.$baseName;
        }

        // Filter out any empty or invalid variations
        $variations = array_filter($variations, function ($username) {
            return ! empty($username) && strlen($username) <= 30;
        });

        // Remove any special characters except underscore and dot
        $variations = array_map(function ($username) {
            $username = preg_replace('/[^a-zA-Z0-9._]/', '', $username);
            // Ensure no consecutive special characters
            $username = preg_replace('/[._]{2,}/', '_', $username);

            // Remove special chars from start and end
            return trim($username, '._');
        }, $variations);

        // Remove duplicates and shuffle
        $variations = array_unique($variations);
        shuffle($variations);

        return array_values($variations);
    }

    public function generateUsername(array $cookies, string $userAgent, string $name): array
    {
        try {
            // Format base name
            $baseName = substr(str_replace(' ', '', $name), 0, 29);

            $headers = [
                'User-Agent' => $userAgent,
                'X-CSRFToken' => $cookies['csrftoken'] ?? '',
                'Referer' => 'https://www.instagram.com/accounts/emailsignup/',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-IG-WWW-Claim' => '0',
                'X-Instagram-AJAX' => '1',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => '*/*',
                'Cookie' => $this->formatCookies($cookies),
            ];

            // First attempt: Try web create ajax with all required fields
            dump('First attempt: Using web create ajax');

            // Generate a temporary email and password for validation
            $tempEmail = $baseName.rand(100, 999).'@temp.com';
            $tempPassword = 'Test12345#'; // Temporary password that meets requirements

            $response = $this->makeRequest('POST', $this->baseUrl.'/web/accounts/web_create_ajax/attempt/', [
                'headers' => $headers,
                'data' => [
                    'enc_password' => '#PWD_INSTAGRAM_BROWSER:0:'.time().':'.$tempPassword,
                    'email' => $tempEmail,
                    'username' => $baseName,
                    'first_name' => $name,
                    'client_id' => $cookies['mid'],
                    'opt_into_one_tap' => 'false',
                ],
            ]);

            $data = json_decode($response->body(), true);
            dump('Web create ajax response:', $data);

            // Check for username suggestions
            if (isset($data['username_suggestions']) && ! empty($data['username_suggestions'])) {
                $username = $data['username_suggestions'][0];
                dump('Got username from suggestions:', $username);

                return [
                    'status' => 'success',
                    'username' => $username,
                ];
            }

            // // If we got here, try with variations
            // dump('Second attempt: Trying variations');
            // $variations = $this->generateUsernameVariations($baseName);
            //
            // foreach ($variations as $attempt => $username) {
            //     dump("Attempt $attempt: Trying username variation - $username");
            //
            //     $response = $this->makeRequest('POST', $this->baseUrl.'/web/accounts/web_create_ajax/attempt/', [
            //         'headers' => $headers,
            //         'data' => [
            //             'enc_password' => '#PWD_INSTAGRAM_BROWSER:0:'.time().':'.$tempPassword,
            //             'email' => $tempEmail,
            //             'username' => $username,
            //             'first_name' => $name,
            //             'client_id' => $cookies['mid'],
            //             'opt_into_one_tap' => 'false',
            //         ],
            //     ]);
            //
            //     $data = json_decode($response->body(), true);
            //
            //     // Check if response contains only username-specific error or no errors
            //     if (
            //         ! isset($data['errors']) ||
            //         (isset($data['errors']) && ! isset($data['errors']['username']))
            //     ) {
            //         dump("Username $username appears valid");
            //
            //         return [
            //             'status' => 'success',
            //             'username' => $username,
            //         ];
            //     }
            //
            //     dump("Username $username not valid, trying next");
            //     usleep(700000); // 0.7 second delay
            // }

            // Last resort: Generate random username
            $randomUsername = $baseName.'_'.substr(md5(uniqid()), 0, 8);
            dump('Falling back to random username:', $randomUsername);

            return [
                'status' => 'success',
                'username' => substr($randomUsername, 0, 30),
            ];
        } catch (\Exception $e) {
            dump('Error generating username:', $e->getMessage());

            return [
                'status' => 'error',
                'details' => 'Username generation failed: '.$e->getMessage(),
            ];
        }
    }

    public function sendEmailVerification($cookies, $email, $deviceId, $userAgent)
    {
        // __AUTO_GENERATED_PRINTF_START__
        dump('Debugging: InstagramHelper#sendEmailVerification 1'); // __AUTO_GENERATED_PRINTF_END__
        $response = Http::withHeaders([
            'User-Agent' => $userAgent,
            'X-CSRFToken' => $cookies['csrftoken'] ?? '',
            'Cookie' => $this->formatCookies($cookies),
        ])->post('https://www.instagram.com/api/v1/accounts/send_verify_email/', [
            'email' => $email,
            'device_id' => $deviceId,
        ]);

        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: InstagramHelper#sendEmailVerification $response->body(): "\n"', $response->body()); // __AUTO_GENERATED_PRINT_VAR_END__
        if ($response->successful() && str_contains($response->body(), '"ok"')) {
            return ['status' => 'success'];
        }

        return [
            'status' => 'error',
            'details' => $response->body(),
        ];
    }

    public function getVerificationCodeFromTempMail($username, $domain)
    {
        $maxAttempts = 3;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $result = $this->tempMailService->getVerificationCode($username, $domain);

            if ($result['status'] === 'success') {
                return $result['code'];
            }

            $attempts++;
            if ($attempts < $maxAttempts) {
                sleep(5); // Wait 5 seconds between attempts
            }
        }

        return null;
    }

    public function verifyEmailCode($cookies, $code, $email, $deviceId)
    {
        $response = Http::withHeaders([
            'User-Agent' => $this->generateUserAgent(),
            'X-CSRFToken' => $cookies['csrftoken'] ?? '',
            'Cookie' => $this->formatCookies($cookies),
        ])->post('https://www.instagram.com/api/v1/accounts/check_confirmation_code/', [
            'code' => $code,
            'email' => $email,
            'device_id' => $deviceId,
        ]);

        if ($response->successful()) {
            $body = $response->body();
            if (str_contains($body, '"signup_code"')) {
                preg_match('/"signup_code":"([^"]+)"/', $body, $matches);

                return [
                    'status' => 'success',
                    'signup_code' => $matches[1] ?? null,
                ];
            }
        }

        return [
            'status' => 'error',
            'details' => $response->body(),
        ];
    }

    public function createAccount($cookies, $name, $username, $password, $signupCode, $email, $deviceId, $userAgent)
    {
        $headers = [
            'User-Agent' => $userAgent,
            'Accept' => '*/*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'X-IG-App-ID' => '936619743392459',
            'X-IG-WWW-Claim' => '0',
            'X-Requested-With' => 'XMLHttpRequest',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'X-ASBD-ID' => '198387',
            'X-IG-Device-ID' => $this->generateUUID(true),
            'X-CSRFToken' => $cookies['csrftoken'] ?? '',
            'X-MID' => $cookies['device_id'] ?? $deviceId,
            'Cookie' => $this->formatCookies($cookies),
        ];

        $response = Http::withHeaders($headers)
            ->post('https://www.instagram.com/api/v1/web/accounts/web_create_ajax/', [
                'enc_password' => '#PWD_INSTAGRAM_BROWSER:0:'.time().':'.$password,
                'email' => $email,
                'username' => $username,
                'first_name' => $name,
                'month' => rand(1, 12),
                'day' => rand(1, 28),
                'year' => rand(1990, 2000),
                'client_id' => $deviceId,
                'seamless_login_enabled' => '1',
                'tos_version' => 'row',
                'force_sign_up_code' => $signupCode,
            ]);

        if ($response->successful() && str_contains($response->body(), '"ok"')) {
            return [
                'status' => 'success',
                'details' => $response->json(),
            ];
        }

        return [
            'status' => 'error',
            'details' => $response->body(),
        ];
    }

    protected function parseCookies($cookieString)
    {
        $cookies = [];
        $parts = explode(';', $cookieString);

        foreach ($parts as $part) {
            $cookiePart = explode('=', trim($part));
            if (count($cookiePart) == 2) {
                $cookies[$cookiePart[0]] = $cookiePart[1];
            }
        }

        return $cookies;
    }
}
