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
        $bjsClient = new BJSClient;
        $bjsService = new BJSService($bjsClient);
        $orderService = new OrderService(new \App\Models\Order, new Redis);
        $utilClient = new UtilClient;

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
     * php artisan test --filter=BJSWrapperTest::test_process_cached_orders
     */
    public function test_process_cached_orders(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $this->wrapper->bjsService->auth();
        // Execute the actual fetch
        $this->wrapper->processCachedOrders();

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_process_orders
     */
    public function test_process_orders(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $this->wrapper->bjsService->auth();
        // Execute the actual fetch
        $this->wrapper->processOrders();

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_resync_orders
     */
    public function test_resync_orders(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $this->wrapper->bjsService->auth();
        // Execute the actual fetch
        $this->wrapper->resyncOrders();

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_set_remains
     */
    public function test_set_remains(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $this->wrapper->bjsService->auth();
        // Execute the actual fetch

        $this->wrapper->bjsService->bjs->changeStatus('4722844', 'pending');

        // NOTE: completed case
        $this->wrapper->bjsService->bjs->setRemains('4722844', 99);

        // // NOTE: completed case
        // $this->wrapper->bjsService->bjs->setRemains('4718465', 1);
        //
        // // NOTE: completed case
        // $this->wrapper->bjsService->bjs->setRemains('4718465', 1);

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_set_startcount
     */
    public function test_set_startcount(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $this->wrapper->bjsService->auth();
        // Execute the actual fetch

        // $this->wrapper->bjsService->bjs->changeStatus('4722844', 'pending');
        $this->wrapper->bjsService->bjs->setStartCount('4722844', 2);

        // // NOTE: completed case
        // $this->wrapper->bjsService->bjs->setRemains('4722844', 99);

        // // NOTE: completed case
        // $this->wrapper->bjsService->bjs->setRemains('4718465', 1);
        //
        // // NOTE: completed case
        // $this->wrapper->bjsService->bjs->setRemains('4718465', 1);

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * To run this test:
     * php artisan test --filter=BJSWrapperTest::test_get_info_order
     */
    public function test_get_info_order(): void
    {
        $this->markTestSkipped('Remove this line to run the test.');

        $this->wrapper->bjsService->auth();
        // Execute the actual fetch
        $this->wrapper->resyncOrders();
        $test = $this->wrapper->bjsService->bjs->getOrderInfo(4747857);
        // __AUTO_GENERATED_PRINT_VAR_START__
        dump('Variable: BJSWrapperTest#test_get_info_order $test: "\n"', $test); // __AUTO_GENERATED_PRINT_VAR_END__

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
        // $this->wrapper->processCachedOrders();
        $this->wrapper->processOrders();
        $this->wrapper->resyncOrders();

        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
}
