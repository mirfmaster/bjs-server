<?php

namespace App\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Http;

class BJSClient
{
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
    }

    public function checkAuth()
    {
        try {
            $testSession = $this->cliXML->request('GET', '/admin/api/general/check-auth');
            $response = (string) $testSession->getBody();
            $responseJson = json_decode($response);

            return $responseJson->data->auth;
        } catch (\Throwable) {
            $this->cookie->clear();

            return false;
        }
    }
}
