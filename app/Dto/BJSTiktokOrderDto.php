<?php

namespace App\Dto;

use DateTime;
use DateTimeInterface;
use JsonSerializable;

class BJSTiktokOrderDto implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $user,
        public readonly float $charge,
        public readonly string $link,
        public readonly int $count,
        public readonly int $service_id,
        public readonly string $status_name,
        public readonly int $status,
        public readonly int $remains,
        public readonly DateTimeInterface $created,
        public readonly ?string $video_id = null,
        public readonly ?string $resolved_url = null
    ) {
    }

    /**
     * Create a DTO from raw API response data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            user: $data['user'],
            charge: (float) $data['charge'],
            link: $data['link'],
            count: (int) $data['count'],
            service_id: (int) $data['service_id'],
            status_name: $data['status_name'],
            status: (int) $data['status'],
            remains: (int) $data['remains'],
            created: new DateTime($data['created']),
            video_id: $data['video_id'] ?? null,
            resolved_url: $data['resolved_url'] ?? null
        );
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'charge' => $this->charge,
            'link' => $this->link,
            'count' => $this->count,
            'service_id' => $this->service_id,
            'status_name' => $this->status_name,
            'status' => $this->status,
            'remains' => $this->remains,
            'created' => $this->created->format('Y-m-d H:i:s'),
            'video_id' => $this->video_id,
            'resolved_url' => $this->resolved_url,
        ];
    }

    /**
     * Implement JsonSerializable interface
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
