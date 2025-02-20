<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TempMailService
{
    protected $baseUrl = 'https://api.internal.temp-mail.io/api/v3';

    protected $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    protected $cookies = '_ga=GA1.2.1099992036.1726242458; __gpi=UID=00000eff7965f4db:T=1726242478:RT=1726257735:S=ALNI_MbLR6cWKQ-IZ7_Ore1SHaBMqWsaSQ; _gid=GA1.2.1165430154.1728797194; _ga_3DVKZSPS3D=GS1.2.1728799480.55.0.1728799480.60.0.0; __gads=ID=fdc72406db773945:T=1726242478:RT=1728799483:S=ALNI_MY68Vfrv0TxdFG747qN6V4pbw3irw; __eoi=ID=5a38d8b4fc1fba25:T=1726242478:RT=1728799483:S=AA-Afjb-58Jhc55qPHFvLWwKhsWS; FCNEC=%5B%5B%22AKsRol_f-QMhupsQZKte8Ts57qd3_oBKbbtBXrsUChj1wEYqngh8eow3w2zem8CV96lBJZMJ43QGXS8g0Juw_CnyM4oExLWzbuCENK65IpdcT6wjqjBE7bFof5Xlx2nrGCdnth2EkiMZHou_pANKlSTbUEAaQ7cY1g%3D%3D%22%5D%5D';

    protected function getHeaders()
    {
        return [
            'accept' => 'application/json, text/plain, */*',
            'accept-language' => 'en-US,en;q=0.9',
            'application-name' => 'web',
            'application-version' => '2.4.1',
            'content-type' => 'application/json;charset=UTF-8',
            'cookie' => $this->cookies,
            'origin' => 'https://temp-mail.io',
            'referer' => 'https://temp-mail.io/',
            'sec-ch-ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-site',
            'user-agent' => $this->userAgent,
        ];
    }

    public function createEmail(string $username, string $domain): ?array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->withoutVerifying()
                ->post("{$this->baseUrl}/email/new", [
                    'name' => $username,
                    'domain' => $domain,
                ]);

            $data = $response->json();

            if (isset($data['token']) && strlen($data['token']) >= 10) {
                return [
                    'email' => "{$username}@{$domain}",
                    'token' => $data['token'],
                    'status' => 'success',
                ];
            }

            return [
                'email' => "{$username}@{$domain} (notoken)",
                'token' => 'notoken',
                'status' => 'error',
            ];
        } catch (\Exception $e) {
            Log::error('TempMail creation failed', [
                'error' => $e->getMessage(),
                'username' => $username,
                'domain' => $domain,
            ]);

            return [
                'details' => 'Error creating email',
                'status' => 'error',
            ];
        }
    }

    public function getVerificationCode(string $username, string $domain): ?array
    {
        try {
            $url = "{$this->baseUrl}/email/{$username}@{$domain}/messages";
            $response = Http::withHeaders($this->getHeaders())
                ->withoutVerifying()
                ->get($url);

            $data = $response->body();

            // Extract code using the same method as original
            if (preg_match('/bottom:25px;"\u003e(.*?)\u003c/', $data, $matches)) {
                $codeRaw = $matches[1];
                $code = preg_replace('/[^0-9]/', '', $codeRaw);

                if (! empty($code)) {
                    return [
                        'code' => $code,
                        'status' => 'success',
                    ];
                }
            }

            return [
                'details' => 'No code found',
                'status' => 'error',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get verification code', [
                'error' => $e->getMessage(),
                'username' => $username,
                'domain' => $domain,
            ]);

            return [
                'details' => 'Error retrieving code',
                'status' => 'error',
            ];
        }
    }
}
