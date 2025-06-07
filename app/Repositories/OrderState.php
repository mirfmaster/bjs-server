<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use Illuminate\Support\Facades\Log;

/**
 * Simple value‐object to hold an order’s Redis‐backed state.
 */
class OrderState
{
    public function __construct(
        public readonly int|string $id,
        public readonly ?OrderStatus $status,
        public readonly int $processing,
        public readonly int $processed,
        public readonly int $duplicateInteraction,
        public readonly int $failed,
        public readonly ?string $firstInteraction,
        public readonly int $requested,
        public readonly ?string $failReason,
    ) {}

    // public function processableS

    public function getRemains(): int
    {
        return $this->processed - $this->requested;
    }

    public function getCompletedRemains(): int
    {
        return max(0, $this->processed - $this->requested);
    }

    // WORKER ORDERSTATE

    // TODO: add worker statistics validation
    public function isExecutable(): bool
    {
        // NOTE:
        // - the problem of doing this is need to update the cached orders when there is no orders
        // - and when the order is only becoming follow only
        $exceedRequest = $this->processed >= $this->requested;
        if ($exceedRequest) {
            Log::debug('Order is exceeding request');

            return false;
        }

        if ($this->failed >= 250) {
            Log::debug('Order is exceeding max fail');

            return false;
        }

        if (! $this->status->isProcessable()) {
            Log::debug('Order status is not processable');

            return false;
        }

        return true;
    }

    public function getCompletionStatus(): OrderStatus
    {
        if ($this->processed >= $this->requested) {
            return OrderStatus::COMPLETED;
        }

        return $this->processed > 0 ? OrderStatus::PARTIAL : OrderStatus::CANCEL;
    }

    /** All the suffixes in the same order we read them from Redis */
    public static function cacheSuffixes(): array
    {
        return [
            'status',
            'processing',
            'processed',
            'duplicate_interaction',
            'failed',
            'first_interaction',
            'requested',
            'fail_reason',
        ];
    }

    /** Build the full Redis keys for a given order ID */
    public static function cacheKeys(int|string $id): array
    {
        return array_map(
            fn(string $suf) => "order:{$id}:{$suf}",
            self::cacheSuffixes()
        );
    }

    /**
     * Hydrate an OrderState from an array of raw Redis values.
     * $raw must be in the same order as cacheSuffixes().
     */
    public static function fromCache(int|string $id, array $raw): self
    {
        [$st, $prc, $prd, $dup, $fld, $first, $req, $failReason] = $raw;

        $status = OrderStatus::tryFrom($st) ?? OrderStatus::UNKNOWN;

        return new self(
            id: $id,
            status: $status,
            processing: (int) $prc,
            processed: (int) $prd,
            duplicateInteraction: (int) $dup,
            failed: (int) $fld,
            firstInteraction: $first,
            requested: (int) $req,
            failReason: $failReason,
        );
    }
}
