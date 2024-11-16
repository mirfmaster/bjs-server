<?php

namespace Tests\Unit\Services;

use App\Client\BJSClient;
use App\Services\BJSService;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;

class BJSServiceTest extends TestCase
{
    protected $bjsClient;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bjsClient = Mockery::mock(BJSClient::class);
        $this->service = new BJSService($this->bjsClient);
    }

    public function test_login_returns_true_when_successful()
    {
        $this->bjsClient->shouldReceive('login')->once()->andReturn(true);

        $result = $this->service->login();

        $this->assertTrue($result);
    }

    public function test_auth_returns_true_when_already_logged_in()
    {
        $this->bjsClient->shouldReceive('checkAuth')->once()->andReturn(true);

        $result = $this->service->auth();

        $this->assertTrue($result);
    }

    public function test_auth_attempts_login_when_not_logged_in()
    {
        $this->bjsClient->shouldReceive('checkAuth')->once()->andReturn(false);
        $this->bjsClient->shouldReceive('login')->once()->andReturn(true);

        $result = $this->service->auth();

        $this->assertTrue($result);
    }

    public function test_get_username_from_simple_input()
    {
        $result = $this->service->getUsername('testuser');
        $this->assertEquals('testuser', $result);
    }

    public function test_get_username_from_instagram_url()
    {
        $result = $this->service->getUsername('https://instagram.com/testuser');
        $this->assertEquals('testuser', $result);
    }

    public function test_get_username_from_reel_url()
    {
        $result = $this->service->getUsername('https://instagram.com/testuser/reel/123456');
        $this->assertEquals('testuser', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
