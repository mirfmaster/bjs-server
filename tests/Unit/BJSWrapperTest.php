<?php

namespace Tests\Unit;

use App\Client\BJSClient;
use App\Client\UtilClient;
use App\Services\BJSService;
use App\Services\OrderService;
use App\Wrapper\BJSWrapper;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BJSWrapperTest extends TestCase
{
    private BJSWrapper $wrapper;

    protected function setUp(): void
    {
        parent::setUp();

        // Create real instances
        $bjsClient = new BJSClient();
        $bjsService = new BJSService($bjsClient);
        $orderService = new OrderService(new \App\Models\Order(), new Redis());
        $utilClient = new UtilClient();

        $this->wrapper = new BJSWrapper(
            $bjsService,
            $orderService,
            $utilClient
        );
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_fetch_like_orders
     */
    public function test_fetch_like_orders(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        // Real service IDs for like orders
        $watchlists = [167];
        $this->wrapper->bjsService->auth();

        // Execute the actual fetch
        $this->wrapper->fetchLikeOrder($watchlists);

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_fetch_follow_orders
     */
    public function test_fetch_follow_orders(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        // Real service IDs for follow orders
        $watchlists = [164];
        $this->wrapper->bjsService->auth();

        // Execute the actual fetch
        $this->wrapper->fetchFollowOrder($watchlists);

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_fetch_all
     */
    public function test_fetch_all(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        // Real service IDs for both like and follow orders
        $followWatchlists = [164];
        $likeWatchlists = [167];
        $this->wrapper->bjsService->auth();

        // Execute both fetches
        $this->wrapper->fetchLikeOrder($likeWatchlists);
        $this->wrapper->fetchFollowOrder($followWatchlists);

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
}
