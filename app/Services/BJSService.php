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
        if (!$isLogin) {
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

        if (!filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        $path = parse_url($input, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        return $pathParts[0] ?? '';
    }
}
