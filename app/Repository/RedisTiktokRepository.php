<?php

namespace App\Repository;

use App\Dto\BJSTiktokOrderDto;
use Illuminate\Support\Facades\Redis;

class RedisTiktokRepository
{
    /**
     * Store a Tiktok order in Redis, avoiding duplicates
     */
    public function storePendingOrder(BJSTiktokOrderDto $order): bool
    {
        $key = 'order:tiktok:pending';

        // Check if this order ID already exists in the set
        $currentOrders = $this->getPendingOrders();
        $orderIds = array_column($currentOrders, 'id');

        if (in_array($order->id, $orderIds)) {
            return false; // Order already exists
        }

        // Add the new order to the list
        $currentOrders[] = $order->toArray();

        // Store the updated list back to Redis
        return (bool) Redis::set($key, json_encode($currentOrders));
    }

    /**
     * Get all pending orders from Redis
     *
     * @return array An array of order data
     */
    public function getPendingOrders(): array
    {
        $key = 'order:tiktok:pending';
        $orders = Redis::get($key);

        if (! $orders) {
            return [];
        }

        return json_decode($orders, true) ?: [];
    }

    /**
     * Get pending orders as DTOs
     *
     * @return BJSTiktokOrderDto[] Array of DTO objects
     */
    public function getPendingOrderDtos(): array
    {
        $ordersData = $this->getPendingOrders();

        return array_map(function ($orderData) {
            return BJSTiktokOrderDto::fromArray($orderData);
        }, $ordersData);
    }

    /**
     * Remove an order from the pending list
     */
    public function removePendingOrder(int $orderId): bool
    {
        $key = 'order:tiktok:pending';
        $currentOrders = $this->getPendingOrders();

        $filteredOrders = array_filter($currentOrders, function ($order) use ($orderId) {
            return $order['id'] !== $orderId;
        });

        // If no orders were removed, return false
        if (count($filteredOrders) === count($currentOrders)) {
            return false;
        }

        return (bool) Redis::set($key, json_encode(array_values($filteredOrders)));
    }

    /**
     * Clear all pending orders
     */
    public function clearPendingOrders(): bool
    {
        $key = 'order:tiktok:pending';

        return (bool) Redis::del($key);
    }
}
