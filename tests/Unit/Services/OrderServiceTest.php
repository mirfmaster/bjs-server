<?php

use Tests\TestCase;
use App\Services\OrderService;
use App\Models\Order;
use Mockery;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class OrderServiceTest extends TestCase
{
    public function test_create_order()
    {
        // Mock Redis
        $redis = Mockery::mock(RedisFactory::class);
        $redis->shouldReceive('set')->times(6);

        // Mock Order
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('create')
            ->once()
            ->andReturn(new Order(['id' => 1]));

        $order->shouldReceive('whereIn->orderBy->orderByRaw->orderBy->limit->get')
            ->once()
            ->andReturn(collect([]));

        $service = new OrderService($order, $redis);

        $data = [
            'requested' => 100,
        ];

        $result = $service->create($data);

        $this->assertInstanceOf(Order::class, $result);
    }
}
