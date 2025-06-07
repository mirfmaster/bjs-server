<?php

namespace App\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

class OrderCacheRepository
{
    public function __construct(
        // Injected via provider
        public readonly CacheRepository $store
    ) {}

    /**
     * Fetch all states for the given order IDs.
     *
     * @param  array<int|string>  $ids
     * @return array<int|string,OrderState>
     */
    public function getStates(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $out = [];

        foreach ($ids as $id) {
            $tag = "order:{$id}";
            $keys = OrderState::cacheKeys($id);

            /** @var array<string,mixed> $raw */
            $raw = $this->store
                ->tags($tag)
                ->many($keys);

            // Extract values in the same order as cacheSuffixes()
            $values = array_map(
                fn (string $key) => $raw[$key] ?? null,
                $keys
            );

            $out[$id] = OrderState::fromCache($id, $values);
        }

        return $out;
    }

    /**
     * Fetch a single order state or null if not present.
     */
    public function getState(int|string $id): ?OrderState
    {
        $states = $this->getStates([$id]);

        return $states[$id] ?? null;
    }

    /**
     * Set the order's status.
     */
    public function setStatus(int|string $id, string $status): void
    {
        $key = "order:{$id}:status";
        $this->store
            ->tags("order:{$id}")
            ->put($key, $status);
    }

    /**
     * Increment the "processing" counter.
     *
     * @return int The new counter value.
     */
    public function incrementProcessing(int|string $id): int
    {
        $key = "order:{$id}:processing";

        return $this->store
            ->tags("order:{$id}")
            ->increment($key);
    }

    /**
     * Increment the "processed" counter.
     */
    public function incrementProcessed(int|string $id): int
    {
        $key = "order:{$id}:processed";

        return $this->store
            ->tags("order:{$id}")
            ->increment($key);
    }

    /**
     * Increment the "duplicate_interaction" counter.
     */
    public function incrementDuplicateInteraction(int|string $id): int
    {
        $key = "order:{$id}:duplicate_interaction";

        return $this->store
            ->tags("order:{$id}")
            ->increment($key);
    }

    /**
     * Increment the "failed" counter.
     */
    public function incrementFailed(int|string $id): int
    {
        $key = "order:{$id}:failed";

        return $this->store
            ->tags("order:{$id}")
            ->increment($key);
    }

    /**
     * Set the first interaction timestamp (only if not set).
     */
    public function setFirstInteraction(int|string $id, int|string $timestamp): void
    {
        $key = "order:{$id}:first_interaction";
        $this->store
            ->tags("order:{$id}")
            // Use put with minutes = null to mimic NX behavior if supported,
            // otherwise guard in your application logic.
            ->add($key, $timestamp, 0);
    }
}
