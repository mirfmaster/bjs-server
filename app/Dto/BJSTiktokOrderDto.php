<?php

namespace App\Dto;

class BJSTiktokOrderDto
{
    /**
     * @param  int  $id  BJS Order ID
     * @param  string  $user  Reseller username
     * @param  string  $link  Original TikTok URL
     * @param  int  $count  Requested quantity
     * @param  string  $video_id  TikTok video ID
     * @param  string|null  $resolved_url  Resolved URL if link was shortened
     * @param  string  $status  Current order status
     * @param  int|null  $start_count  Initial view/like count
     * @param  int|null  $processed_count  Number of successfully processed
     * @param  int|null  $created_at  Timestamp when order was created
     * @param  int|null  $started_at  Timestamp when processing started
     * @param  int|null  $completed_at  Timestamp when processing completed
     * @param  int|null  $bjs_completed_at  Timestamp when order was marked complete in BJS
     */
    public function __construct(
        public int $id,
        public string $user,
        public string $link,
        public int $count,
        public string $video_id,
        public ?string $resolved_url = null,
        public string $status = 'pending',
        public ?int $start_count = null,
        public ?int $processed_count = null,
        public ?int $created_at = null,
        public ?int $started_at = null,
        public ?int $completed_at = null,
        public ?int $bjs_completed_at = null
    ) {
        // Set created_at to current timestamp if not provided
        if ($this->created_at === null) {
            $this->created_at = time();
        }
    }

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        // Convert date string to timestamp if needed
        $created_at = isset($data['created_at'])
            ? (is_string($data['created_at']) ? strtotime($data['created_at']) : $data['created_at'])
            : time();

        $started_at = isset($data['started_at'])
            ? (is_string($data['started_at']) ? strtotime($data['started_at']) : $data['started_at'])
            : null;

        $completed_at = isset($data['completed_at'])
            ? (is_string($data['completed_at']) ? strtotime($data['completed_at']) : $data['completed_at'])
            : null;

        $bjs_completed_at = isset($data['bjs_completed_at'])
            ? (is_string($data['bjs_completed_at']) ? strtotime($data['bjs_completed_at']) : $data['bjs_completed_at'])
            : null;

        return new self(
            id: $data['id'],
            user: $data['user'],
            link: $data['link'],
            count: $data['count'],
            video_id: $data['video_id'],
            resolved_url: $data['resolved_url'] ?? null,
            status: $data['status_name'] ?? $data['status'] ?? 'pending',
            start_count: $data['start_count'] ?? null,
            processed_count: $data['processed_count'] ?? null,
            created_at: $created_at,
            started_at: $started_at,
            completed_at: $completed_at,
            bjs_completed_at: $bjs_completed_at
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
            'link' => $this->link,
            'count' => $this->count,
            'video_id' => $this->video_id,
            'resolved_url' => $this->resolved_url,
            'status' => $this->status,
            'start_count' => $this->start_count,
            'processed_count' => $this->processed_count,
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'bjs_completed_at' => $this->bjs_completed_at,
        ];
    }

    /**
     * Check if the order has been completed
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Check if the order has been completed in BJS
     */
    public function isCompletedInBJS(): bool
    {
        return $this->bjs_completed_at !== null;
    }

    /**
     * Mark the order as completed
     */
    public function markCompleted(?int $processed_count = null): self
    {
        $this->status = 'completed';
        $this->completed_at = time();

        if ($processed_count !== null) {
            $this->processed_count = $processed_count;
        }

        return $this;
    }

    /**
     * Mark the order as completed in BJS
     */
    public function markCompletedInBJS(): self
    {
        $this->bjs_completed_at = time();

        return $this;
    }
}

