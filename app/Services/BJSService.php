<?php

namespace App\Services;

use App\Client\BJSClient;
use App\Traits\LoggerTrait;

class BJSService
{
    use LoggerTrait;
    public $likeServiceList = [165];
    public function __construct(public BJSClient $bjs) {}

    public function login()
    {
        $resp = $this->bjs->login();

        return !!$resp;
    }

    public function auth()
    {
        $isLogin = $this->bjs->checkAuth();
        if (!$isLogin) {
            $login = $this->login();
            if ($login) {
                return true;
            }

            return false;
        }

        return true;
    }


    public function getOrdersData($serviceId, $status, $pageSize = 100)
    {
        try {
            $request = $this->bjs
                ->cliXML
                ->get("/admin/api/orders/list?status=$status&service=$serviceId&page_size=$pageSize");
            $resp =  json_decode($request->getBody(), false);

            $orders = collect($resp->data->orders);

            return $orders;
        } catch (\Throwable $th) {
            $this->logError($th);
            return [];
        }
    }

    // NOTE: HELPER
    public function getUsername($link)
    {
        $input = str_replace('@', '', $link);

        // Replace /reel/ and /tv/ with /p/ in the URL
        $input = str_replace(['/reel/', '/tv/'], '/p/', $input);

        if (!filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        $path = parse_url($input, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        return $pathParts[0];
    }
}
