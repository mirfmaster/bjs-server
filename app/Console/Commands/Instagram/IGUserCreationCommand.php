<?php

namespace App\Console\Commands\Instagram;

use App\Repository\IGRegisterRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IGUserCreationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:createv2 {--max-attempts=3 : Maximum email verification attempts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Instagram account with automatic retry on failure';

    protected IGRegisterRepository $repo;

    protected string $userAgent;

    protected string $deviceId;

    protected array $cookies = [];

    protected array $passwordEnc = [];

    protected array $tokens = [];

    /**
     * Constructor
     */
    public function __construct(IGRegisterRepository $repo)
    {
        parent::__construct();
        $this->repo = $repo;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Instagram Account Creation...');

        try {
            $this->initializeSession();

            $accountDetails = $this->prepareAccountDetails();

            $maxAttempts = (int) $this->option('max-attempts');
            $attemptCount = 0;
            $success = false;

            while (! $success && $attemptCount < $maxAttempts) {
                $attemptCount++;
                $this->info("Attempt {$attemptCount} of {$maxAttempts}");

                try {
                    $email = $accountDetails['email'];
                    if ($this->sendVerificationEmail($email)) {
                        $verificationCode = $this->getVerificationCode();
                        $checkConfirmationCode = Http::withHeaders([
                            'User-Agent' => $this->userAgent,
                            'X-CSRFToken' => $this->cookies['csrftoken'] ?? '',
                            'Cookie' => $this->formatCookies($this->cookies),
                        ])
                            ->withOptions([
                                'timeout' => 10,
                                'connect_timeout' => 5,
                                'proxy' => $this->getProxy(),
                            ])
                            ->post('https://www.instagram.com/api/v1/accounts/check_confirmation_code/', [
                                'code' => $verificationCode,
                                'email' => $email,
                                'device_id' => $this->deviceId,
                            ]);

                        $checkBody = $checkConfirmationCode->json();
                        dump([
                            'checkConfirmationCode' => $checkBody,
                        ]);

                        if ($checkConfirmationCode->ok()) {
                            $signupCode = $checkBody['signup_code'];
                            $success = $this->createAccount($accountDetails, $signupCode);
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("Attempt {$attemptCount} failed: " . $e->getMessage());

                    if ($attemptCount >= $maxAttempts) {
                        throw $e;
                    }

                    $this->warn('Retrying in 5 seconds...');
                    sleep(5);
                }
            }

            if ($success) {
                $this->info('Account created successfully!');
                $this->info("Username: {$accountDetails['username']}");
                $this->info("Password: {$accountDetails['password']}");
                $this->info("Email: {$accountDetails['email']}");

                return Command::SUCCESS;
            }

            $this->error('Failed to create account after multiple attempts');

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Instagram account creation failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Initialize session with cookies and user agent
     *
     * @throws \Exception
     */
    protected function initializeSession(): void
    {
        $this->userAgent = $this->repo->generateUserAgent();

        $this->info('Getting initial session...');

        // $this->userAgent = $userAgent;
        $cookies = $this->getInstagramCookies($this->userAgent);
        $this->cookies = $cookies;
        $this->formatCookies($this->cookies);
        $this->deviceId = $cookies['mid'];

        $initialRequest = Http::withHeaders([
            'User-Agent' => $this->userAgent,
        ])
            ->withOptions([
                'timeout' => 10,
                'connect_timeout' => 5,
                'proxy' => $this->getProxy(),
            ])
            ->get('https://www.instagram.com/accounts/emailsignup/');
        $response = $initialRequest->body();
        $passwordEnc = $this->extractPasswordEncryptionTokens($response);

        $password = 'IBJSG' . rand(1000, 999999);
        $reqEncrypt = Http::get('http://139.162.52.90:8088/encrypt', [
            'key_id' => $passwordEnc['key_id'],
            'pub_key' => $passwordEnc['public_key'],
            'password' => $password,
        ]);
        throw_if($reqEncrypt->failed(), 'Encryt password failed');

        $passwordEnc['encrypted'] = $reqEncrypt->body();
        $passwordEnc['plain'] = $password;

        $this->passwordEnc = $passwordEnc;
        $tokens = $this->extractInstagramTokens($response);
        $this->tokens = $tokens;
        dump([
            'userAgent' => $this->userAgent,
            'cookies' => $this->cookies,
            'passwordEnc' => $this->passwordEnc,
            'tokens' => $this->tokens,
        ]);

        if (! $this->cookies || empty($this->cookies['csrftoken']) || empty($this->cookies['mid'])) {
            throw new \Exception('Failed to get required cookies. Got: ' . json_encode($this->cookies));
        }
    }

    public function generateDeviceId(string $seed): string
    {
        // Set timezone to Jakarta
        config(['app.timezone' => 'Asia/Jakarta']);

        // Get current timestamp
        $timestamp = now()->timestamp;

        // Create a device ID by combining the seed and timestamp
        return 'android-' . substr(md5($seed . $timestamp), 16);
    }

    /**
     * Prepare account details for registration
     */
    protected function prepareAccountDetails(): array
    {
        $name = $this->repo->getRandomName();
        $this->info("Using name: $name");

        // $unameVariations = $this->repo->get($name['name']);
        $unameVariations = $this->repo->getUsernameVariations($name);
        $username = $unameVariations[7];

        $email = $this->ask('Enter Email');

        $this->info("Using email and username: $email | $username");

        return [
            'email' => $email,
            'username' => $username,
            'name' => $name,
            'month' => rand(1, 12),
            'day' => rand(1, 28),
            'year' => rand(1990, 2000),
        ];
    }

    /**
     * Send verification email
     */
    protected function sendVerificationEmail(string $email): bool
    {
        $this->info('Sending verification email...');

        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'X-CSRFToken' => $this->cookies['csrftoken'] ?? '',
            'Cookie' => $this->formatCookies($this->cookies),
        ])
            ->withOptions([
                'timeout' => 10,
                'connect_timeout' => 5,
                'proxy' => $this->getProxy(),
            ])
            ->post('https://www.instagram.com/api/v1/accounts/send_verify_email/', [
                'email' => $email,
                'device_id' => $this->deviceId,
            ]);

        if (! $response->successful() && ! str_contains($response->body(), '"ok"')) {
            $this->error('Failed to send verification email: ' . $response->body());

            return false;
        }

        $this->info('Verification email sent successfully');

        return true;
    }

    /**
     * Get verification code from user input
     */
    protected function getVerificationCode(): string
    {
        $this->info('Waiting for verification code...');

        return $this->ask('Please enter the verification code received in email');
    }

    /**
     * Create Instagram account
     */
    protected function createAccount(array $accountDetails, string $verificationCode): bool
    {
        $requestBody = [
            'enc_password' => $this->passwordEnc['encrypted'],
            'email' => $accountDetails['email'],
            'username' => $accountDetails['username'],
            'first_name' => $accountDetails['name'],
            'month' => $accountDetails['month'],
            'day' => $accountDetails['day'],
            'year' => $accountDetails['year'],
            'client_id' => $this->deviceId,
            'seamless_login_enabled' => '1',
            'tos_version' => 'row',
            'force_sign_up_code' => $verificationCode,
        ];
        $headers = $this->getRequestHeaders();
        dump('Creating account...', [
            'headers' => $headers,
            'requestBody' => $requestBody,
        ]);

        $response = Http::withHeaders($headers)
            ->withOptions([
                'timeout' => 10,
                'connect_timeout' => 5,
                'proxy' => $this->getProxy(),
            ])
            ->post('https://www.instagram.com/api/v1/web/accounts/web_create_ajax/', $requestBody);

        $responseBody = $response->body();
        $this->info('Response: ' . $responseBody);

        return $response->successful() && str_contains($responseBody, '"ok"');
    }

    /**
     * Get request headers for API calls
     */
    protected function getRequestHeaders(): array
    {
        return [
            'User-Agent' => $this->userAgent,
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
            'X-CSRFToken' => $this->cookies['csrftoken'] ?? '',
            'X-MID' => $this->cookies['device_id'] ?? $this->deviceId,
            'Cookie' => $this->formatCookies($this->cookies),
        ];
    }

    /**
     * Get proxy configuration
     */
    protected function getProxy(): string
    {
        return 'igcreate1-zone-resi-region-id-asn-AS7713:igcreator6969@d169f2e23873ee25.tuf.as.pyproxy.io:16666';
    }

    /**
     * Format cookies for HTTP header
     */
    protected function formatCookies(array $cookies): string
    {
        $cookie = 'Cookie: rur=FTW; ig_did=' . $cookies['ig_did'] . ';csrftoken=' . $cookies['csrftoken'] . ';mid=' . $cookies['mid'];

        return $cookie;
    }

    /**
     * Generate UUID
     */
    protected function generateUUID(bool $keepDashes = true): string
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

    protected function extractPasswordEncryptionTokens(string $html): array
    {
        $encryptionTokens = [
            'key_id' => null,
            'public_key' => null,
            'version' => null,
        ];

        // Method 1: Try to find it in the specific InstagramPasswordEncryption format
        $pattern = '/\["InstagramPasswordEncryption",\s*\[\s*\],\s*\{\s*"key_id":\s*"([^"]+)",\s*"public_key":\s*"([^"]+)",\s*"version":\s*"([^"]+)"\s*\}/s';

        if (preg_match($pattern, $html, $matches)) {
            if (count($matches) >= 4) {
                $encryptionTokens['key_id'] = $matches[1];
                $encryptionTokens['public_key'] = $matches[2];
                $encryptionTokens['version'] = $matches[3];

                return $encryptionTokens;
            }
        }

        return $encryptionTokens;
    }

    public function extractInstagramTokens(string $html): array
    {
        $tokens = [
            'csrfToken' => null,
            'lsdToken' => null,
            'appId' => null,
            'jazoest' => null,
            'hsi' => null,
        ];

        // Extract CSRF token
        if (preg_match('/"csrf_token":\s*"([^"]+)"/', $html, $csrfMatch)) {
            $tokens['csrfToken'] = $csrfMatch[1];
        }

        // Extract LSD token
        if (preg_match('/"lsd":\s*"([^"]+)"/', $html, $lsdMatch)) {
            $tokens['lsdToken'] = $lsdMatch[1];
        }

        // Extract App ID
        if (preg_match('/"X-IG-App-ID":"(\d+)"/i', $html, $appIdMatch)) {
            $tokens['appId'] = $appIdMatch[1];
        }

        // Extract jazoest
        if (preg_match('/<script id="__eqmc"[^>]*>([\s\S]*?)<\/script>/', $html, $eqmcScriptMatch)) {
            try {
                $scriptContent = trim($eqmcScriptMatch[1]);
                $jsonContent = json_decode($scriptContent, true);
                if (! empty($jsonContent) && ! empty($jsonContent['u'])) {
                    $parts = explode('?', $jsonContent['u']);
                    if (count($parts) > 1) {
                        parse_str($parts[1], $params);
                        if (! empty($params['jazoest'])) {
                            $tokens['jazoest'] = $params['jazoest'];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently continue if JSON parsing fails
            }
        }

        // Extract HSI parameter
        if (preg_match('/"hsi":\s*"([^"]+)"/', $html, $hsiMatch)) {
            $tokens['hsi'] = $hsiMatch[1];
        }

        return $tokens;
    }

    public function getInstagramCookies(string $userAgent, ?string $proxy = null): array
    {
        // Make request to Instagram
        $ch = curl_init('https://i.instagram.com/api/v1/web/accounts/login/ajax/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

        // Add proxy configuration if provided
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);

            // If proxy requires authentication (format: username:password@host:port)
            if (strpos($proxy, '@') !== false) {
                $proxyAuth = substr($proxy, 0, strpos($proxy, '@'));
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
            }
        }

        $cookies = [];

        // Cookie parsing callback
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$cookies) {
            $length = strlen($header);
            $header = explode(':', $header, 2);

            // Extract only Set-Cookie headers
            if (count($header) >= 2 && strtolower(trim($header[0])) === 'set-cookie') {
                $cookieStr = trim($header[1]);

                // Parse the cookie string to get name=value part
                $cookieParts = explode(';', $cookieStr);
                if (! empty($cookieParts[0])) {
                    [$name, $value] = explode('=', $cookieParts[0], 2);
                    $name = trim($name);
                    $value = trim($value);

                    // Store in our cookies array
                    $cookies[$name] = $value;

                    // For debugging - extract cookie attributes
                    $attributes = [];
                    for ($i = 1; $i < count($cookieParts); $i++) {
                        $part = trim($cookieParts[$i]);
                        if (strpos($part, '=') !== false) {
                            [$attrName, $attrValue] = explode('=', $part, 2);
                            $attributes[trim($attrName)] = trim($attrValue);
                        } else {
                            $attributes[$part] = true;
                        }
                    }

                    // Store domain and expiry information if needed
                    if (isset($attributes['domain'])) {
                        $cookies[$name . '_domain'] = $attributes['domain'];
                    }

                    if (isset($attributes['expires'])) {
                        $cookies[$name . '_expires'] = $attributes['expires'];
                    }
                }
            }

            return $length;
        });

        $response = curl_exec($ch);
        curl_close($ch);

        return $cookies;
    }
}
