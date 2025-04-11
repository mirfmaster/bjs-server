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

    /**
     * Get all processed orders from Redis
     *
     * @return array An array of order data
     */
    public function getProcessedOrders(): array
    {
        $key = 'order:tiktok:processed';
        $orders = Redis::get($key);

        if (! $orders) {
            return [];
        }

        return json_decode($orders, true) ?: [];
    }

    /**
     * Get processed orders as DTOs
     *
     * @return BJSTiktokOrderDto[] Array of DTO objects
     */
    public function getProcessedOrderDtos(): array
    {
        $ordersData = $this->getProcessedOrders();

        return array_map(function ($orderData) {
            return BJSTiktokOrderDto::fromArray($orderData);
        }, $ordersData);
    }

    /**
     * Store an order in the processed list
     * This is used by the worker server when it finishes processing an order
     */
    public function storeProcessedOrder(BJSTiktokOrderDto $order): bool
    {
        $key = 'order:tiktok:processed';
        $currentOrders = $this->getProcessedOrders();

        // Check if order already exists in processed list
        $orderIds = array_column($currentOrders, 'id');

        // If it exists, remove the old entry
        if (in_array($order->id, $orderIds)) {
            $currentOrders = array_filter($currentOrders, function ($item) use ($order) {
                return $item['id'] !== $order->id;
            });
        }

        // Ensure completion time is set
        $orderData = $order->toArray();
        if (! isset($orderData['completed_at'])) {
            $orderData['completed_at'] = now()->timestamp;
        }

        // Add to processed list
        $currentOrders[] = $orderData;

        return (bool) Redis::set($key, json_encode(array_values($currentOrders)));
    }

    /**
     * Move an order from processed to completed
     * Returns the moved order data if successful, null otherwise
     */
    public function moveToCompleted(int $orderId): ?array
    {
        $processedOrders = $this->getProcessedOrders();

        // Find the order in processed list
        $orderKey = null;
        $orderData = null;

        foreach ($processedOrders as $key => $order) {
            if ($order['id'] === $orderId) {
                $orderKey = $key;
                $orderData = $order;
                break;
            }
        }

        if ($orderData === null) {
            return null; // Order not found in processed list
        }

        // Remove from processed list
        unset($processedOrders[$orderKey]);
        Redis::set('order:tiktok:processed', json_encode(array_values($processedOrders)));

        // Add to completed list with completion time
        if (! isset($orderData['bjs_completed_at'])) {
            $orderData['bjs_completed_at'] = now()->timestamp;
        }

        $this->addToCompletedList($orderData);

        return $orderData;
    }

    /**
     * Add an order to the completed list
     */
    private function addToCompletedList(array $orderData): bool
    {
        $key = 'order:tiktok:completed';
        $completedOrders = $this->getCompletedOrders();

        // Add to completed list
        $completedOrders[] = $orderData;

        // Keep only the last 1000 completed orders to prevent the list from growing too large
        if (count($completedOrders) > 1000) {
            $completedOrders = array_slice($completedOrders, -1000);
        }

        return (bool) Redis::set($key, json_encode($completedOrders));
    }

    /**
     * Get all completed orders from Redis
     */
    public function getCompletedOrders(): array
    {
        $key = 'order:tiktok:completed';
        $orders = Redis::get($key);

        if (! $orders) {
            return [];
        }

        return json_decode($orders, true) ?: [];
    }

    /**
     * Update an order's status and metadata without changing its location
     */
    public function updateOrderStatus(int $orderId, string $status, array $additionalData = []): bool
    {
        // Check in pending list first
        $pendingOrders = $this->getPendingOrders();
        foreach ($pendingOrders as $key => $order) {
            if ($order['id'] === $orderId) {
                $pendingOrders[$key]['status'] = $status;

                // Add any additional data
                foreach ($additionalData as $dataKey => $dataValue) {
                    $pendingOrders[$key][$dataKey] = $dataValue;
                }

                return (bool) Redis::set('order:tiktok:pending', json_encode($pendingOrders));
            }
        }

        // Then check processed list
        $processedOrders = $this->getProcessedOrders();
        foreach ($processedOrders as $key => $order) {
            if ($order['id'] === $orderId) {
                $processedOrders[$key]['status'] = $status;

                // Add any additional data
                foreach ($additionalData as $dataKey => $dataValue) {
                    $processedOrders[$key][$dataKey] = $dataValue;
                }

                return (bool) Redis::set('order:tiktok:processed', json_encode($processedOrders));
            }
        }

        return false; // Order not found in either list
    }
}

