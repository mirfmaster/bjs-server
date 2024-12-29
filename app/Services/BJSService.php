<?php

namespace App\Services;

use App\Client\BJSClient;
use App\Traits\LoggerTrait;
use Illuminate\Support\Collection;

class BJSService
{
    use LoggerTrait;

    public $likeServiceList = [165];

    public function __construct(
        public BJSClient $bjs
    ) {
    }

    public function login(): bool
    {
        $resp = $this->bjs->login();

        return (bool) $resp;
    }

    public function auth(): bool
    {
        $isLogin = $this->bjs->checkAuth();
        if (! $isLogin) {
            return $this->login();
        }

        return true;
    }

    public function getOrdersData(int $serviceId, int $status, int $pageSize = 100): Collection
    {
        try {
            $request = $this->bjs->getOrdersList($status, $serviceId, $pageSize);
            $resp = json_decode($request->getBody(), false);

            return collect($resp->data->orders);
        } catch (\Throwable $th) {
            $this->logError($th);

            return collect([]);
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

        return $pathParts[0] ?? '';
    }

    /**
     * Extract either username or media code from an Instagram URL
     * Examples:
     * - https://www.instagram.com/p/CfRQdvbBRT3/?img_index=1 -> CfRQdvbBRT3
     * - https://www.instagram.com/muhamadiqbal.idn/ -> muhamadiqbal.idn
     * - @muhamadiqbal.idn -> muhamadiqbal.idn
     *
     * @param  string  $link  Instagram URL or username
     * @return string Username or media code
     */
    public function extractIdentifier(string $link): string
    {
        // Remove @ prefix if present
        $input = str_replace('@', '', $link);

        // If not a valid URL, assume it's a username
        if (! filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        // Parse URL and get path
        $path = parse_url($input, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        // If path contains /p/, /reel/, or /tv/, return the media code
        if (in_array($pathParts[0], ['p', 'reel', 'tv'])) {
            return $pathParts[1] ?? '';
        }

        // Otherwise return the username
        return $pathParts[0] ?? '';
    }
}
