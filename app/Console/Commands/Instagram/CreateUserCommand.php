<?php

namespace App\Console\Commands\Instagram;

use App\Repository\IgRegisterRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:createv3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rewrite from viqhril class';

    public function __construct(public IgRegisterRepository $repo)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    private $userAgent;

    public function handle()
    {
        $this->info('starting command create account');
        // $account = $this->prepareAccountDetails();
        $this->userAgent = $this->repo->generateUserAgent();
        //
        // $this->info('Getting initial session...');
        // $initialRequest = Http::withHeaders([
        //     'User-Agent' => $this->userAgent,
        // ])
        //     ->withOptions([
        //         'timeout' => 10,
        //         'connect_timeout' => 5,
        //         'proxy' => $this->getProxy(),
        //     ])
        //     ->get('https://www.instagram.com/accounts/emailsignup/');

        // $this->userAgent = $userAgent;
        $cookies = $this->getInstagramCookies($this->userAgent);
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: CreateUserCommand#handle $cookies: "\n"', $cookies); // __AUTO_GENERATED_PRINT_VAR_END__
        // $this->cookies = $cookies;
        // $this->deviceId = $cookies['mid'];
        //
        // $response = $initialRequest->body();
        // $passwordEnc = $this->extractPasswordEncryptionTokens($response);
        //
        // $password = 'IBJSG' . rand(1000, 999999);
        // $reqEncrypt = Http::get('http://139.162.52.90:8088/encrypt', [
        //     'key_id' => $passwordEnc['key_id'],
        //     'pub_key' => $passwordEnc['public_key'],
        //     'password' => $password,
        // ]);
        // throw_if($reqEncrypt->failed(), 'Encryt password failed');
        //
        // $passwordEnc['encrypted'] = $reqEncrypt->body();
        // $passwordEnc['plain'] = $password;
        //
        // $this->passwordEnc = $passwordEnc;
        // $tokens = $this->extractInstagramTokens($response);
        // $this->tokens = $tokens;
        // dump([
        //     'userAgent' => $this->userAgent,
        //     'cookies' => $this->cookies,
        // ]);

        return Command::SUCCESS;
    }

    /**
     * Prepare account details for registration
     */
    protected function prepareAccountDetails(): array
    {
        $profile = $this->repo->getRandomName();
        $username = $this->repo->getUsername($profile['name']);

        $email = $this->ask('Enter Email');
        $password = 'IBJSG' . rand(1000, 999999);

        $this->info("Using email and username: $email | $username");

        return [
            'email' => $email,
            'username' => $username,
            'name' => $profile['name'],
            'month' => rand(1, 12),
            'day' => rand(1, 28),
            'year' => rand(1990, 2000),
            'password' => $password,
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
            // 'Cookie' => $this->formatCookies($this->cookies),
        ])
            ->withOptions([
                'timeout' => 10,
                'connect_timeout' => 5,
                'proxy' => $this->getProxy(),
            ])
            ->post('https://www.instagram.com/api/v1/accounts/send_verify_email/', [
                'email' => $email,
                // 'device_id' => $this->deviceId,
            ]);

        if (! $response->successful() && ! str_contains($response->body(), '"ok"')) {
            $this->error('Failed to send verification email: ' . $response->body());

            return false;
        }

        $this->info('Verification email sent successfully');

        return true;
    }

    public function getInstagramCookies(string $userAgent): array
    {
        // Make request to Instagram
        $ch = curl_init('https://i.instagram.com/api/v1/web/accounts/login/ajax/');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

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
