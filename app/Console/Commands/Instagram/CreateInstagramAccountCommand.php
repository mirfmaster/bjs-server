<?php

namespace App\Console\Commands\Instagram;

use App\Repository\IGCreateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateInstagramAccountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:create-account {--proxy= : HTTP proxy to use for requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Instagram account using email verification';

    /**
     * The Instagram creation repository.
     *
     * @var IGCreateRepository
     */
    protected $igRepository;

    /**
     * User agent for requests
     *
     * @var string
     */
    protected $userAgent;

    /**
     * Proxy server to use (format: http://username:password@ip:port)
     *
     * @var string|null
     */
    protected $proxy;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(IGCreateRepository $igRepository)
    {
        parent::__construct();
        $this->igRepository = $igRepository;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Instagram Account Creator by Viqhril (Laravel Edition)');
        $this->line('================================================');

        // Set up proxy if provided
        $this->proxy = $this->option('proxy');
        $this->proxy = 'igcreate1-zone-resi-region-id-asn-AS7713:igcreator6969@d169f2e23873ee25.tuf.as.pyproxy.io:16666';
        if ($this->proxy) {
            $this->info('Using proxy: ' . $this->maskProxyCredentials($this->proxy));
            $this->igRepository->setProxy($this->proxy);
        }

        // Set up initial details and cookies
        $this->userAgent = $this->igRepository->getRandomMobileUserAgent();
        $this->line('Initializing session...');
        $this->line($this->userAgent);

        // Initialize Instagram session to get cookies
        $httpClient = Http::withHeaders([
            'User-Agent' => $this->userAgent,
        ]);

        if ($this->proxy) {
            $httpClient = $httpClient->withOptions(['proxy' => $this->proxy]);
        }

        $cookie = $this->getInstagramCookies($this->userAgent, $this->proxy);

        // $response = $httpClient->get('https://i.instagram.com/api/v1/web/accounts/login/ajax/');
        //
        // if (! $response->successful()) {
        //     // __AUTO_GENERATED_PRINT_VAR_START__
        //     dump('Variable: CreateInstagramAccountCommand#handle#if $response: "\n"', $response->body()); // __AUTO_GENERATED_PRINT_VAR_END__
        //     $this->error('Failed to initialize Instagram session');
        //
        //     return 1;
        // }
        //
        // $cookie = $this->igRepository->extractCookiesFromResponse($response);
        $deviceId = $cookie['mid'] ?? null;

        if (! $deviceId) {
            $this->error('Failed to obtain device ID from Instagram');

            return 1;
        }

        $this->info('Session initialized successfully');
        $this->line('Cookie: ' . json_encode($cookie));

        // Generate random account name
        $name = $this->igRepository->generateRandomName();
        $this->info("Generated name: $name");
        $email = $this->ask('Input email: ');

        // Generate username from Instagram
        // $this->info('Generating username suggestion from Instagram...');
        // $getUsernameResult = $this->igRepository->getUsernameSuggestion($cookie, $name, $email);

        // if ($getUsernameResult['status'] === 'success' && ! empty($getUsernameResult['username'])) {
        //     $username = $getUsernameResult['username'];
        //     $this->info("Generated username: $username");
        // } else {
        //     $this->error('Failed to generate username: ' . ($getUsernameResult['details'] ?? 'Unknown error'));
        //     // Generate a fallback username
        //     $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
        //     $this->info("Using fallback username: $username");
        // }
        $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
        $this->info("Using fallback username: $username");

        // Generate a secure password
        $randomString = $this->igRepository->generateRandomString();
        $date = now()->format('ymd');
        $password = "R3ndo0mz{$randomString}{$date}";
        $this->info("Password: $password");

        // Send OTP verification
        $this->info('Sending verification code to email...');
        $sendOtpResult = $this->igRepository->sendEmailVerification($cookie, $email, $deviceId, $this->userAgent, $this->proxy);

        if ($sendOtpResult['status'] !== 'success') {
            $this->error('Failed to send verification code: ' . ($sendOtpResult['details'] ?? 'Unknown error'));

            return 1;
        }

        $this->info('Verification code sent successfully!');

        // Ask for OTP code
        $this->info('Please check your email for verification code');
        $otpCode = $this->ask('Enter verification code');

        $this->info("Verifying code: $otpCode");

        // Verify OTP code
        $verifyCodeResult = $this->igRepository->verifyEmailCode($cookie, $otpCode, $email, $deviceId, $this->proxy);

        if ($verifyCodeResult['status'] !== 'success') {
            $this->error('Failed to verify code: ' . ($verifyCodeResult['details'] ?? 'Invalid code'));

            return 1;
        }

        $signupCode = $verifyCodeResult['signup_code'];
        $this->info('Code verified successfully');

        // Create the account
        $this->info('Creating Instagram account...');
        $createAccountResult = $this->igRepository->createAccount(
            $cookie,
            $name,
            $username,
            $password,
            $signupCode,
            $email,
            $deviceId,
            $this->userAgent,
            $this->proxy
        );

        if ($createAccountResult['status'] !== 'success') {
            $this->error('Failed to create account: ' . ($createAccountResult['details'] ?? 'Unknown error'));

            return 1;
        }

        // Success output
        $this->newLine();
        $this->info('Account created successfully!');
        $this->line('================================================');
        $this->info('Account Details:');
        $this->line("Full Name: $name");
        $this->line("Username: $username");
        $this->line("Password: $password");
        $this->line("Email: $email");

        // Save account details to a log file
        $this->saveAccountDetails($createAccountResult);

        return 0;
    }

    /**
     * Save account details to log file
     *
     * @param  array  $accountData
     * @return void
     */
    protected function saveAccountDetails($accountData)
    {
        $logContent = json_encode([
            'date_created' => now()->format('Y-m-d H:i:s'),
            'username' => $accountData['username'],
            'password' => $accountData['password'],
            'email' => $accountData['email'],
            'full_name' => $accountData['fullname'],
        ]);

        Log::channel('instagram')->info('New account created', ['data' => $logContent]);

        $this->info('Account details have been saved to log');
    }

    /**
     * Mask proxy credentials for display
     *
     * @param  string  $proxy
     * @return string
     */
    protected function maskProxyCredentials($proxy)
    {
        if (strpos($proxy, '@') !== false) {
            $parts = explode('@', $proxy);
            $credentialsPart = $parts[0];
            $serverPart = $parts[1];

            if (strpos($credentialsPart, '://') !== false) {
                $protocolParts = explode('://', $credentialsPart);
                $protocol = $protocolParts[0] . '://';
                $credentials = $protocolParts[1];

                // Mask the credentials part
                return $protocol . '****:****@' . $serverPart;
            }

            return '****:****@' . $serverPart;
        }

        return $proxy;
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
