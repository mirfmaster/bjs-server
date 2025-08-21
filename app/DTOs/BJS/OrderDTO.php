<?php

namespace App\DTOs\BJS;

use App\Enums\BJSOrderStatus;

class OrderDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $user,
        public readonly string $charge,
        public readonly string $link,
        public readonly string $statusName,
        public readonly int $statusCode,
        public readonly int $startCount,
        public readonly int $count,
        public readonly int $serviceId,
        public readonly string $serviceName,
        public readonly string $remains,
        public readonly string $created
    ) {}

    /**
     * Create a DTO from the raw BJS order data
     */
    public static function fromBJSOrder(object $bjsOrder): self
    {
        return new self(
            id: $bjsOrder->id,
            user: $bjsOrder->user,
            charge: $bjsOrder->charge,
            link: $bjsOrder->link,
            statusName: $bjsOrder->status_name,
            statusCode: $bjsOrder->status,
            startCount: $bjsOrder->start_count,
            count: $bjsOrder->count,
            serviceId: $bjsOrder->service_id,
            serviceName: $bjsOrder->service_name,
            remains: $bjsOrder->remains,
            created: $bjsOrder->created
        );
    }

    /**
     * Convert the DTO to an array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'charge' => $this->charge,
            'link' => $this->link,
            'status_name' => $this->statusName,
            'status_code' => $this->statusCode,
            'start_count' => $this->startCount,
            'count' => $this->count,
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'remains' => $this->remains,
            'created' => $this->created,
        ];
    }

    /**
     * Check if this is a like order based on service name
     */
    public function isLikeOrder(): bool
    {
        return stripos($this->serviceName, 'like') !== false;
    }

    /**
     * Check if this is a follow order based on service name
     */
    public function isFollowOrder(): bool
    {
        return stripos($this->serviceName, 'follow') !== false;
    }

    public function getStatusEnum(): BJSOrderStatus
    {
        return BJSOrderStatus::tryFrom($this->statusCode);
    }

    public function getProcessed(): int
    {
        return $this->count - $this->remains;
    }
}
