<?php

namespace Tests\Unit\Client;

use Tests\TestCase;
use App\Client\BJSClient;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;

class BJSClientTest extends TestCase
{
    private MockHandler $mockHandler;
    private MockHandler $mockXMLHandler;
    private BJSClient $bjsClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped();
        // Create mock handlers
        $this->mockHandler = new MockHandler();
        $this->mockXMLHandler = new MockHandler();

        // Create handler stacks
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStackXML = HandlerStack::create($this->mockXMLHandler);

        // Mock config values
        config(['app.bjs_api' => 'https://example.com']);
        config(['app.bjs_username' => 'testuser']);
        config(['app.bjs_password' => 'testpass']);

        // Create a mock cookie jar
        $mockCookieJar = $this->createMock(FileCookieJar::class);

        // Create the BJSClient instance with mocked dependencies
        $this->bjsClient = new BJSClient();

        // Replace the clients with mocked versions
        $this->bjsClient->cli = new Client(['handler' => $handlerStack]);
        $this->bjsClient->cliXML = new Client(['handler' => $handlerStackXML]);
    }

    public function testSuccessfulLogin()
    {
        // Mock the CSRF token response
        $this->mockHandler->append(
            new Response(200, [], '<html><meta name="csrf-token" content="test-csrf-token"></html>')
        );

        // Mock the login response
        $this->mockHandler->append(
            new Response(200, [], 'Login successful')
        );

        $result = $this->bjsClient->login();
        $this->assertTrue($result);
    }

    public function testLoginFailsWithoutCSRFToken()
    {
        // Mock response without CSRF token
        $this->mockHandler->append(
            new Response(200, [], '<html>No token here</html>')
        );

        $result = $this->bjsClient->login();
        $this->assertFalse($result);
    }

    public function testSuccessfulAuthCheck()
    {
        // Mock successful auth check response
        $this->mockXMLHandler->append(
            new Response(200, [], json_encode([
                'data' => ['auth' => true]
            ]))
        );

        $result = $this->bjsClient->checkAuth();
        $this->assertTrue($result);
    }

    public function testFailedAuthCheck()
    {
        // Mock failed auth check response
        $this->mockXMLHandler->append(
            new Response(200, [], json_encode([
                'data' => ['auth' => false]
            ]))
        );

        $result = $this->bjsClient->checkAuth();
        $this->assertFalse($result);
    }

    public function testAuthCheckWithException()
    {
        // Mock an exception during auth check
        $this->mockXMLHandler->append(
            new RequestException(
                'Error Communicating with Server',
                new \GuzzleHttp\Psr7\Request('GET', 'test'),
                new Response(500)
            )
        );

        $result = $this->bjsClient->checkAuth();
        $this->assertFalse($result);
    }

    public function testExtractTokenReturnsNullForInvalidHTML()
    {
        $reflection = new \ReflectionClass(BJSClient::class);
        $method = $reflection->getMethod('extractToken');
        $method->setAccessible(true);

        $result = $method->invoke($this->bjsClient, '<html>Invalid HTML without token</html>');
        $this->assertNull($result);
    }

    public function testExtractTokenReturnsTokenForValidHTML()
    {
        $reflection = new \ReflectionClass(BJSClient::class);
        $method = $reflection->getMethod('extractToken');
        $method->setAccessible(true);

        $html = '<html><meta name="csrf-token" content="valid-token"></html>';
        $result = $method->invoke($this->bjsClient, $html);
        $this->assertEquals('valid-token', $result);
    }
}
