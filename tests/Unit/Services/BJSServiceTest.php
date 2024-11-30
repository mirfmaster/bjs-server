<?php

namespace Tests\Unit\Services;

use App\Client\BJSClient;
use App\Services\BJSService;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class BJSServiceTest extends TestCase
{
    private $bjsClient;
    private $bjsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock for BJSClient
        $this->bjsClient = Mockery::mock(BJSClient::class);

        // Mock the config for LoggerTrait
        config(['app.debug' => false]);

        // Initialize service with mock client
        $this->bjsService = new BJSService($this->bjsClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_returns_true_on_successful_login()
    {
        // Arrange
        $this->bjsClient
            ->shouldReceive('login')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->bjsService->login();

        // Assert
        $this->assertTrue($result);
    }

    public function test_login_returns_false_on_failed_login()
    {
        // Arrange
        $this->bjsClient
            ->shouldReceive('login')
            ->once()
            ->andReturn(false);

        // Act
        $result = $this->bjsService->login();

        // Assert
        $this->assertFalse($result);
    }

    public function test_auth_returns_true_when_already_authenticated()
    {
        // Arrange
        $this->bjsClient
            ->shouldReceive('checkAuth')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->bjsService->auth();

        // Assert
        $this->assertTrue($result);
    }

    public function test_auth_attempts_login_when_not_authenticated()
    {
        // Arrange
        $this->bjsClient
            ->shouldReceive('checkAuth')
            ->once()
            ->andReturn(false);

        $this->bjsClient
            ->shouldReceive('login')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->bjsService->auth();

        // Assert
        $this->assertTrue($result);
    }

    public function test_getOrdersData_returns_collection_of_orders()
    {
        // Arrange
        $mockResponse = Mockery::mock(ResponseInterface::class);
        $mockStream = Mockery::mock(StreamInterface::class);

        $mockStream
            ->shouldReceive('__toString')
            ->andReturn(json_encode([
                'data' => [
                    'orders' => [
                        ['id' => 1, 'status' => 'pending'],
                        ['id' => 2, 'status' => 'completed']
                    ]
                ]
            ]));

        $mockResponse
            ->shouldReceive('getBody')
            ->andReturn($mockStream);

        $this->bjsClient
            ->shouldReceive('getOrdersList')
            ->with(0, 165, 100)
            ->once()
            ->andReturn($mockResponse);

        // Act
        $result = $this->bjsService->getOrdersData(165, 0, 100);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(2, $result->count());
        $this->assertEquals(1, $result[0]->id);
        $this->assertEquals('pending', $result[0]->status);
    }

    public function test_getOrdersData_returns_empty_collection_on_error()
    {
        // Arrange
        $this->bjsClient
            ->shouldReceive('getOrdersList')
            ->once()
            ->andThrow(new \Exception('API Error'));

        // Act
        $result = $this->bjsService->getOrdersData(165, 0, 100);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    /**
     * @dataProvider usernameDataProvider
     */
    public function test_getUsername_returns_expected_username($input, $expected)
    {
        // Act
        $result = $this->bjsService->getUsername($input);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function usernameDataProvider()
    {
        return [
            'simple_username' => [
                'johndoe',
                'johndoe'
            ],
            'username_with_at' => [
                '@johndoe',
                'johndoe'
            ],
            'instagram_post_url' => [
                'https://www.instagram.com/johndoe/p/ABC123',
                'johndoe'
            ],
            'instagram_reel_url' => [
                'https://www.instagram.com/johndoe/reel/ABC123',
                'johndoe'
            ],
            'instagram_tv_url' => [
                'https://www.instagram.com/johndoe/tv/ABC123',
                'johndoe'
            ],
            'empty_path_parts' => [
                'https://www.instagram.com/',
                ''
            ]
        ];
    }
}

