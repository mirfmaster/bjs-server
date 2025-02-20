<?php

namespace App\Console\Commands;

use App\Helpers\InstagramHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateInstagramAccount extends Command
{
    protected $signature = 'instagram:create-account
                            {--name= : Full name for the account}
                            {--username= : Desired username (optional)}
                            {--email= : Custom email (optional)}';

    // @deprecated @see research_create_account
    protected $description = 'Create a new Instagram account via email (DEPRECATED)';

    protected InstagramHelper $instagramHelper;

    public function __construct(InstagramHelper $instagramHelper)
    {
        parent::__construct();
        $this->instagramHelper = $instagramHelper;
    }

    public function handle()
    {
        $this->info('Starting Instagram Account Creation...');

        try {
            // Initialize basic data
            $userAgent = $this->instagramHelper->generateUserAgent();
            $deviceId = $this->instagramHelper->generateDeviceId();

            // Get initial cookies with proper error handling
            $this->info('Getting initial session...');
            $cookies = $this->instagramHelper->getInitialCookies($userAgent);

            if (! $cookies || empty($cookies['csrftoken']) || empty($cookies['mid'])) {
                throw new \Exception('Failed to get required cookies. Got: '.json_encode($cookies));
            }

            $this->info('Successfully obtained cookies: '.json_encode($cookies));

            // Get or generate name
            $name = $this->option('name') ?: $this->instagramHelper->generateRandomName();
            $this->info("Using name: $name");

            // // Get or generate username
            $username = $this->option('username');
            if (! $username) {
                $usernameData = $this->instagramHelper->generateUsername($cookies, $userAgent, $name);
                if ($usernameData['status'] !== 'success') {
                    throw new \Exception('Failed to generate username: '.($usernameData['details'] ?? 'Unknown error'));
                }
                $username = $usernameData['username'];
            }
            $this->info("Using username: $username");

            $email = $this->option('email');
            if (! $email) {
                $tempMailDomain = $this->instagramHelper->getRandomTempMailDomain();
                $emailUsername = $username.rand(100, 999);

                // Create temporary email
                $this->info('Creating temporary email address...');
                $createEmailResult = $this->instagramHelper->tempMailService->createEmail($emailUsername, $tempMailDomain);

                if ($createEmailResult['status'] !== 'success') {
                    throw new \Exception('Failed to create temporary email: '.($createEmailResult['details'] ?? 'Unknown error'));
                }

                $email = $createEmailResult['email'];
                $this->info("Successfully created temp email: $email");
            }
            $this->info("Using email: $email");

            // Generate password
            $password = 'IBJSG'.rand(100000, 999999).'#'.date('Ymd');

            // Send email verification
            $this->info('Sending email verification...');
            $sendOtpResult = $this->instagramHelper->sendEmailVerification($cookies, $email, $deviceId, $userAgent);

            if ($sendOtpResult['status'] !== 'success') {
                throw new \Exception('Failed to send verification email: '.($sendOtpResult['details'] ?? 'Unknown error'));
            }

            // Wait for email verification code
            $this->info('Waiting for verification code...');
            $this->output->write('Waiting.');
            //
            // If using temp mail, attempt to get code automatically
            if (! $this->option('email')) {
                sleep(15); // Wait for email to arrive
                $emailParts = explode('@', $email);
                $tempUsername = $emailParts[0];
                $tempDomain = $emailParts[1];

                $attempts = 0;
                $maxAttempts = 3;
                $verificationCode = null;

                while ($attempts < $maxAttempts && ! $verificationCode) {
                    $codeResult = $this->instagramHelper->tempMailService->getVerificationCode($tempUsername, $tempDomain);
                    if ($codeResult['status'] === 'success') {
                        $verificationCode = $codeResult['code'];
                        break;
                    }
                    $attempts++;
                    if ($attempts < $maxAttempts) {
                        sleep(5);
                        $this->output->write('.');
                    }
                }
            } else {
                $verificationCode = $this->ask('Please enter the verification code received in email');
            }

            if (! $verificationCode) {
                throw new \Exception('Failed to get verification code');
            }

            $this->info("\nReceived verification code: $verificationCode");

            // Verify the code
            $this->info('Verifying code...');
            $verifyResult = $this->instagramHelper->verifyEmailCode(
                $cookies,
                $verificationCode,
                $email,
                $deviceId
            );

            if ($verifyResult['status'] !== 'success') {
                throw new \Exception('Failed to verify code: '.($verifyResult['details'] ?? 'Unknown error'));
            }

            // Create the account
            $this->info('Creating account...');
            $createResult = $this->instagramHelper->createAccount(
                $cookies,
                $name,
                $username,
                $password,
                $verifyResult['signup_code'],
                $email,
                $deviceId,
                $userAgent
            );

            if ($createResult['status'] !== 'success') {
                throw new \Exception('Failed to create account: '.($createResult['details'] ?? 'Unknown error'));
            }

            // Success output
            $this->info('Account created successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Username', $username],
                    ['Password', $password],
                    ['Email', $email],
                    ['Full Name', $name],
                ]
            );

            // Save to log file
            $logData = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'name' => $name,
                'created_at' => now()->toString(),
            ];

            $logFile = storage_path('logs/instagram_accounts.json');
            $existingLogs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            $existingLogs[] = $logData;
            file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('Instagram account creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        return 0;
    }
}
