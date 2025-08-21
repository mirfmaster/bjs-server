<?php

namespace App\Models;

use App\Enums\OrderStatus;

final class OrderState
{
    public function __construct(
        public readonly int|string $id,
        public readonly ?OrderStatus $status,
        public readonly int $processing,
        public readonly int $processed,
        public readonly int $duplicateInteraction,
        public readonly int $failed,
        public readonly ?int $firstInteraction,
        public readonly int $requested,
        public readonly ?string $failReason,
    ) {}

    public function isValid(): int
    {
        return $this->requested > 0;
    }

    public function remains(): int
    {
        return max(0, $this->requested - $this->processed);
    }

    public function isExecutable(): bool
    {
        return $this->remains() > 0
            && $this->failed < 250
            && $this->status?->isProcessable();
    }

    public function completionStatus(): OrderStatus
    {
        if ($this->processed === 0 && $this->processing === 0) {
            return OrderStatus::CANCEL;
        }

        if ($this->processed >= $this->requested) {
            return OrderStatus::COMPLETED;
        }

        return $this->processed > 0
            ? OrderStatus::PARTIAL
            : OrderStatus::CANCEL;
    }

    /**
     * Hydrate from an array of raw Redis values.
     * Order must match the suffix list used by OrderCache.
     */
    public static function fromRaw(int|string $id, array $raw): self
    {
        [$st, $prc, $prd, $dup, $fld, $first, $req, $reason] = $raw;

        return new self(
            id: $id,
            status: OrderStatus::tryFrom($st),
            processing: (int) $prc,
            processed: (int) $prd,
            duplicateInteraction: (int) $dup,
            failed: (int) $fld,
            firstInteraction: $first === null ? null : (int) $first,
            requested: (int) $req,
            failReason: $reason,
        );
    }
}
