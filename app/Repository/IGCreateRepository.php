<?php

namespace App\Repository;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IGCreateRepository
{
    /**
     * User agents array placeholder
     * Will be filled with actual user agents by the user
     */
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

    /**
     * Current proxy configuration
     */
    protected $proxy = null;

    /**
     * Extract string between two strings
     */
    public function getStringBetween($string, $start, $end)
    {
        $str = explode($start, $string);
        if (isset($str[1])) {
            $str = explode($end, $str[1]);

            return $str[0];
        }

        return '';
    }

    /**
     * Extract string using split method
     */
    public function splitString($string, $separator, $secondSeparator)
    {
        $parts = explode($separator, $string);
        if (isset($parts[1])) {
            $secondParts = explode($secondSeparator, $parts[1]);

            return $secondParts[0];
        }

        return '';
    }

    /**
     * Generate UUID for authentication
     */
    public function generateUUID($withHyphens = false)
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

        return $withHyphens ? $uuid : str_replace('-', '', $uuid);
    }

    /**
     * Generate device ID for Instagram
     */
    public function generateDeviceId($seed)
    {
        $volatileSeed = now()->timestamp;

        return 'android-' . substr(md5($seed . $volatileSeed), 16);
    }

    /**
     * Extract cookies from HTTP response
     */
    public function extractCookiesFromResponse($response)
    {
        $cookies = [];

        if ($response->headers()->has('Set-Cookie')) {
            $cookieHeaders = $response->headers()->get('Set-Cookie');

            // Handle both array and string formats
            if (! is_array($cookieHeaders)) {
                $cookieHeaders = [$cookieHeaders];
            }

            foreach ($cookieHeaders as $cookieStr) {
                preg_match('/^([^;]+)/', $cookieStr, $matches);
                if (isset($matches[1])) {
                    [$name, $value] = explode('=', $matches[1], 2);
                    $cookies[$name] = $value;
                }
            }
        }

        return $cookies;
    }

    /**
     * Generate random Indonesian name using a third-party API
     */
    public function generateRandomName()
    {
        $response = Http::post('http://ninjaname.horseridersupply.com/indonesian_name.php', [
            'number_generate' => 30,
            'gender_type' => 'female',
            'submit' => 'Generate',
        ]);

        if ($response->successful()) {
            preg_match_all('~(&bull; (.*?)<br/>&bull; )~', $response->body(), $names);
            if (isset($names[2]) && ! empty($names[2])) {
                return $names[2][mt_rand(0, min(14, count($names[2]) - 1))];
            }
        }

        // Fallback if API fails
        $names = ['Ayu', 'Dewi', 'Siti', 'Putri', 'Rina', 'Dian', 'Lina', 'Maya', 'Indah', 'Sri'];

        return $names[array_rand($names)];
    }

    /**
     * Generate a username suggestion from Instagram
     */
    public function getUsernameSuggestion($cookie, $name, $email)
    {
        $url = 'https://www.instagram.com/accounts/web_create_ajax/attempt/';

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/12.0 Mobile/15A372 Safari/604.1',
            'X-CSRFToken' => $cookie['csrftoken'],
            'Referer' => 'https://www.instagram.com/accounts/emailsignup/',
            'Cookie' => 'ig_did=' . ($cookie['ig_did'] ?? '') . ';csrftoken=' . ($cookie['csrftoken'] ?? '') . ';mid=' . ($cookie['mid'] ?? ''),
        ])
            ->withOptions([
                'proxy' => $this->getProxy(),
            ])
            ->post($url, [
                'enc_password' => '#PWD_INSTAGRAM_BROWSER:0:1590871866:',
                'email' => $email,
                'phone_number' => '',
                'username' => $this->formatUsername($name),
                'first_name' => str_replace(' ', '+', $name),
                'client_id' => $cookie['mid'] ?? '',
                'opt_into_one_tap' => 'false',
            ]);

        $responseBody = $response->body();

        // If the first method fails, try the alternative method
        if (str_contains($responseBody, '403 Forbidden') || str_contains($responseBody, '429 Too Many Requests')) {
            $urlCheck = 'https://www.instagram.com/api/v1/users/check_username/';

            $response = Http::withHeaders([
                'accept' => '*/*',
                'accept-language' => 'en-US,en;q=0.9',
                'content-type' => 'application/x-www-form-urlencoded',
                'cookie' => 'ig_did=' . ($cookie['ig_did'] ?? '') . ';csrftoken=' . ($cookie['csrftoken'] ?? '') . ';mid=' . ($cookie['mid'] ?? ''),
                'origin' => 'https://www.instagram.com',
                'referer' => 'https://www.instagram.com/accounts/signup/username/',
                'user-agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
                'x-csrftoken' => $cookie['csrftoken'] ?? '',
                'x-ig-app-id' => '1217981644879628',
                'x-instagram-ajax' => '1014841970',
                'x-requested-with' => 'XMLHttpRequest',
            ])->post($urlCheck, [
                'username' => $this->formatUsername($name),
            ]);

            $responseBody = $response->body();

            if (str_contains($responseBody, '"status":"ok"')) {
                $username = $this->getStringBetween($responseBody, '"suggestions":["', '",');

                return [
                    'action' => 'create',
                    'status' => 'success',
                    'username' => $username,
                ];
            }

            return [
                'action' => 'create',
                'status' => 'error',
                'details' => $responseBody,
            ];
        } elseif (str_contains($responseBody, 'Please wait')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'Please wait a few minutes before you try again',
            ];
        } elseif (str_contains($responseBody, '"spam":true')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'Session Spam',
            ];
        } elseif (str_contains($responseBody, '"status": "ok"')) {
            $username = $this->getStringBetween($responseBody, 'username_suggestions": ["', '",');

            return [
                'action' => 'create',
                'status' => 'success',
                'username' => $username,
                'details' => $responseBody,
            ];
        } else {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => $responseBody,
            ];
        }
    }

    /**
     * Format username to meet Instagram requirements
     */
    private function formatUsername($name)
    {
        $formatted = str_replace(' ', '', $name);

        // Limit to maximum 29 characters
        return strlen($formatted) > 29 ? substr($formatted, 0, 29) : $formatted;
    }

    /**
     * Set proxy configuration
     *
     * @param  string|null  $proxy  Proxy in format 'http://username:password@ip:port' or null to disable
     * @return $this
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;

        return $this;
    }

    /**
     * Get current proxy configuration
     *
     * @return string|null
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * Send OTP verification to email
     */
    public function sendEmailVerification($cookie, $email, $deviceId, $userAgent, $proxy = null)
    {
        $url = 'https://www.instagram.com/api/v1/accounts/send_verify_email/';

        $httpClient = Http::withHeaders([
            'User-Agent' => $userAgent,
            'X-CSRFToken' => $cookie['csrftoken'],
            'Referer' => 'https://www.instagram.com/accounts/signup/email/',
            'Cookie' => 'ig_did=' . ($cookie['ig_did'] ?? '') . ';csrftoken=' . ($cookie['csrftoken'] ?? '') . ';mid=' . ($cookie['mid'] ?? ''),
        ])
            ->withOptions(['proxy' => $this->proxy]);

        $response = $httpClient->post($url, [
            'email' => $email,
            'device_id' => $deviceId,
        ]);

        $responseBody = $response->body();

        try {
            $responseJson = $response->json();
            if (isset($responseJson['status']) && $responseJson['status'] === 'ok') {
                return [
                    'action' => 'create',
                    'status' => 'success',
                ];
            }
        } catch (\Exception $e) {
            // Fall back to string parsing if JSON parsing fails
            if (str_contains($responseBody, '"ok"')) {
                return [
                    'action' => 'create',
                    'status' => 'success',
                ];
            }
        }

        if (str_contains($responseBody, 'Please wait')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'Please wait a few minutes before you try again',
            ];
        }

        return [
            'action' => 'create',
            'status' => 'error',
            'details' => $responseBody,
        ];
    }

    /**
     * Verify OTP code sent to email
     */
    public function verifyEmailCode($cookie, $code, $email, $deviceId, $proxy = null)
    {
        $url = 'https://www.instagram.com/api/v1/accounts/check_confirmation_code/';

        $httpClient = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/12.0 Mobile/15A372 Safari/604.1',
            'X-CSRFToken' => $cookie['csrftoken'],
            'Referer' => 'https://www.instagram.com/accounts/signup/emailConfirmation/',
            'Cookie' => 'ig_did=' . ($cookie['ig_did'] ?? '') . ';csrftoken=' . ($cookie['csrftoken'] ?? '') . ';mid=' . ($cookie['mid'] ?? ''),
        ]);

        // Apply proxy if provided or if class proxy is set
        $proxyToUse = $proxy ?? $this->proxy;
        if ($proxyToUse) {
            $httpClient = $httpClient->withOptions(['proxy' => $proxyToUse]);
        }

        $response = $httpClient->post($url, [
            'code' => $code,
            'email' => $email,
            'device_id' => $deviceId,
        ]);

        $responseBody = $response->body();

        try {
            $responseJson = $response->json();
            if (isset($responseJson['status']) && $responseJson['status'] === 'ok') {
                $signupCode = $responseJson['signup_code'] ?? '';
                if (empty($signupCode)) {
                    $signupCode = $this->splitString($responseBody, '"signup_code":"', '",');
                }

                return [
                    'action' => 'create',
                    'signup_code' => $signupCode,
                    'status' => 'success',
                ];
            }
        } catch (\Exception $e) {
            // Fall back to string parsing if JSON parsing fails
            if (str_contains($responseBody, '"ok"')) {
                $signupCode = $this->splitString($responseBody, '"signup_code":"', '",');

                return [
                    'action' => 'create',
                    'signup_code' => $signupCode,
                    'status' => 'success',
                ];
            }
        }

        if (str_contains($responseBody, 'Please wait')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'Please wait a few minutes before you try again',
            ];
        } elseif (str_contains($responseBody, 'invalid_nonce')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'Invalid code',
            ];
        }

        return [
            'action' => 'create',
            'status' => 'error',
            'details' => $responseBody,
        ];
    }

    /**
     * Create Instagram account
     */
    public function createAccount($cookie, $name, $username, $password, $signupCode, $email, $deviceId, $userAgent, $proxy = null)
    {
        $url = 'https://www.instagram.com/api/v1/web/accounts/web_create_ajax/';

        $month = rand(1, 12);
        $day = rand(1, 28);
        $year = rand(1990, 2000);

        $httpClient = Http::withHeaders([
            'User-Agent' => $userAgent,
            'X-CSRFToken' => $cookie['csrftoken'],
            'Referer' => 'https://www.instagram.com/accounts/signup/username/',
            'Cookie' => 'rur=FTW; ig_did=' . ($cookie['ig_did'] ?? '') . ';csrftoken=' . ($cookie['csrftoken'] ?? '') . ';mid=' . ($cookie['mid'] ?? ''),
        ])
            ->withOptions(['proxy' => $this->proxy]);
        $requestBody = [
            'enc_password' => '#PWD_INSTAGRAM_BROWSER:0:1590871866:' . $password,
            'day' => $day,
            'email' => $email,
            'first_name' => $name,
            'month' => $month,
            'username' => $username,
            'year' => $year,
            'client_id' => $deviceId,
            'seamless_login_enabled' => 1,
            'tos_version' => 'row',
            'force_sign_up_code' => $signupCode,
        ];
        dump($requestBody);

        $response = $httpClient->post($url, $requestBody);

        $responseBody = $response->body();

        try {
            $responseJson = $response->json();
            if (isset($responseJson['account_created']) && $responseJson['account_created'] === true) {
                $cookieLog = $this->extractCookiesFromResponse($response);

                return [
                    'action' => 'create',
                    'status' => 'success',
                    'fullname' => $name,
                    'username' => $username,
                    'password' => $password,
                    'email' => $email,
                    'cookie' => $cookieLog,
                    'details' => $responseJson,
                ];
            }
        } catch (\Exception $e) {
            // If JSON parsing fails, fall back to string parsing
        }

        // Fall back to string-based checking if JSON parsing fails or doesn't meet criteria
        if (str_contains($responseBody, 'Please wait')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'Please wait a few minutes before you try again',
            ];
        } elseif (str_contains($responseBody, '1.1 572')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => '572 Limit Useragent',
            ];
        } elseif (str_contains($responseBody, '429 Too Many Requests')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => '429 Too Many Requests',
            ];
        } elseif (str_contains($responseBody, 'signup_block')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'signup_block',
            ];
        } elseif (str_contains($responseBody, '"sentry_block_restriction_dialogue_unification_enabled":true')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'sentry_block_restriction_dialogue_unification_enabled',
            ];
        } elseif (str_contains($responseBody, 'scraping')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'limit scraping',
            ];
        } elseif (str_contains($responseBody, 'There was an error with your request. Please try again')) {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => 'There was an error with your request. Please try again.',
            ];
        } elseif (str_contains($responseBody, 'an error occurred')) {
            return [
                'action' => 'create',
                'status' => 'success',
                'details' => $responseBody,
            ];
        } elseif (str_contains($responseBody, '"ok"')) {
            // __AUTO_GENERATED_PRINT_VAR_START__
            dump('Variable: IGCreateRepository#createAccount#if $responseBody: "\n"', $responseBody); // __AUTO_GENERATED_PRINT_VAR_END__
            // $cookieLog = $this->extractCookiesFromResponse($response);

            return [
                'action' => 'create',
                'status' => 'success',
                'fullname' => $name,
                'username' => $username,
                'password' => $password,
                'email' => $email,
                // 'cookie' => $cookieLog,
                'details' => $responseBody,
            ];
        } else {
            return [
                'action' => 'create',
                'status' => 'error',
                'details' => $responseBody,
            ];
        }
    }

    /**
     * Generate random string for password creation
     */
    public function generateRandomString($length = 5)
    {
        return Str::random($length);
    }

    /**
     * Get a random mobile user agent
     */
    public function getRandomMobileUserAgent()
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
}
