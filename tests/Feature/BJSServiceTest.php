<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BJSService;
use App\Client\BJSClient;

class BJSServiceTest extends TestCase
{
    protected BJSService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new BJSClient();
        $this->service = new BJSService($client);
    }

    public function test_login_and_reuse_cookie()
    {
        $auth = $this->service->auth();
        $this->assertTrue($auth, "Assert login to be true");

        $auth = $this->service->auth();
        $this->assertTrue($auth, "Assert reusing is okay");
    }

    /**
     * @dataProvider usernameTestCases
     */
    public function testGetUsername($input, $expected)
    {
        $result = $this->service->getUsername($input);
        $this->assertEquals($expected, $result);
    }

    public function usernameTestCases()
    {
        return [
            'simple username' => [
                'johndoe',
                'johndoe'
            ],
            'username with @' => [
                '@johndoe',
                'johndoe'
            ],
            'instagram profile url' => [
                'https://instagram.com/johndoe',
                'johndoe'
            ],
            'instagram post url' => [
                'https://instagram.com/johndoe/p/ABC123',
                'johndoe'
            ],
            'instagram reel url' => [
                'https://instagram.com/johndoe/reel/ABC123',
                'johndoe'
            ],
            'instagram tv url' => [
                'https://instagram.com/johndoe/tv/ABC123',
                'johndoe'
            ],
            'complex url with query params' => [
                'https://instagram.com/johndoe/p/ABC123?utm_source=test',
                'johndoe'
            ],
        ];
    }

    public function testGetOrdersDataWithInvalidService()
    {
        $auth = $this->service->auth();
        $this->assertTrue($auth, "Assert login to be true");

        $orders = $this->service->getOrdersData(99999, 0); // Invalid service ID
        $this->assertIsArray($orders->toArray()); // Convert collection to array
        $this->assertEmpty($orders);
    }

    public function testGetOrdersData()
    {
        // Test with valid service ID from likeServiceList
        $serviceId = $this->service->likeServiceList[0];
        $orders = $this->service->getOrdersData($serviceId, 0);

        // Dump for debugging
        dump('Orders response:', $orders);

        // Assert it's a collection
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $orders);
    }
}
