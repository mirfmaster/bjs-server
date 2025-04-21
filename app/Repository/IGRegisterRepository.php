<?php

namespace App\Repository;

use Illuminate\Support\Facades\Http;

class IGRegisterRepository
{
    protected $baseUrl = 'https://www.instagram.com/api/v1';

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

    private ?string $proxy;

    public function setProxy(string $proxy)
    {
        $this->proxy = $proxy;
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

    public function generateUserAgent()
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    public function getRandomName()
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

    public function getUsernameVariations(string $baseName): array
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
}
