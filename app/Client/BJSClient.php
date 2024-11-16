<?php

namespace App\Client;

use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class BJSClient
{
    use LoggerTrait;

    public \GuzzleHttp\Client $cli;
    public \GuzzleHttp\Client $cliXML;
    // TODO: move into http
    public Http $http;

    public $loginState = false;
    public $isValidSession = false;
    private FileCookieJar $cookie;

    public function __construct()
    {
        $this->cookie = new FileCookieJar(storage_path('app/bjs-cookies.json'), true);

        $url = config('app.bjs_api');

        $this->cli = new Client([
            'cookies' => $this->cookie,
            'base_uri' => $url,
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => 'https://belanjasosmed.com/admin/orders?status=0&service=11',
                'Origin' => $url,
            ],
        ]);

        $this->cliXML = new Client([
            'cookies' => $this->cookie,
            'base_uri' => $url,
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => 'https://belanjasosmed.com/admin/orders?status=0&service=11',
                'Origin' => $url,
            ],
        ]);
    }

    private function getCSRFToken()
    {
        $req = $this->cli->request('GET', '/admin');
        $html = (string) $req->getBody();

        return $this->extractToken($html);
    }

    private function extractToken($html)
    {
        $pattern = '/<meta name="csrf-token" content="(.*?)">/';
        preg_match($pattern, $html, $matches);

        return $matches[1] ?? null;
    }

    // @return int
    public function login()
    {
        try {
            $csrf = $this->getCSRFToken();
            if ($csrf == null) {
                return false;
            }

            $username = config('app.bjs_username');
            $password = config('app.bjs_password');
            $req = $this->cli->request('POST', '/admin', [
                'headers' =>             [
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => 'https://belanjasosmed.com/admin',
                    'Origin' => 'https://belanjasosmed.com/',
                ],
                'form_params' => [
                    '_csrf_admin' => $csrf,
                    'SignInForm[login]' => $username,
                    'SignInForm[password]' => $password,
                    'SignInForm[remember]' => 1,
                ],
            ]);

            $code = $req->getStatusCode();

            return $code === 200;
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function checkAuth()
    {
        try {
            $testSession = $this->cliXML->request('GET', '/admin/api/general/check-auth');
            $response = (string) $testSession->getBody();
            $responseJson = json_decode($response);

            return $responseJson->data->auth;
        } catch (\Throwable $th) {
            $this->cookie->clear();
            $this->logError($th);

            return false;
        }
    }

    public function getInfo($orderId): object
    {
        try {
            $request = $this->cliXML->get("/admin/api/orders/get-info/$orderId?type=2&num=0");
            $response = (string) $request->getBody();
            $reqJson = json_decode($response, false);
            return $reqJson;
        } catch (\Throwable $th) {
            $this->logError($th);
            return (object)[];
        }
    }

    public function setStartCount($id, $start)
    {
        try {
            return $this->cliXML->post('/admin/api/orders/set-start-count/' . $id, [
                'form_params' => [
                    'start_count' => $start,
                ],
            ]);
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function setRemains($id, $remains)
    {
        try {
            return $this->cliXML->post('/admin/api/orders/set-remains/' . $id, [
                'form_params' => [
                    'remains' => $remains,
                ],
            ]);
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function cancelOrder($id)
    {
        try {
            return $this->cliXML->post('/admin/api/orders/cancel/' . $id);
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function changeStatus($id, $status)
    {
        try {
            return $this->cliXML->post('/admin/api/orders/change-status/' . $id, [
                'form_params' => [
                    'status' => $status,
                ],
            ]);
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

    public function changeStatusServices($id, bool $status)
    {
        try {
            return $this->cliXML->post('/admin/api/services/change-status/' . $id, [
                'form_params' => [
                    'status' => $status,
                ],
            ]);
        } catch (\Throwable $th) {
            $this->logError($th);

            return false;
        }
    }

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

    public function getOrdersList(int $status, int $serviceId, int $pageSize): ResponseInterface
    {
        return $this->cliXML->get("/admin/api/orders/list?status=$status&service=$serviceId&page_size=$pageSize");
    }
}
