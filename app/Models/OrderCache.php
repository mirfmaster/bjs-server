<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

final class OrderCache
{
    public const SUFFIXES = [
        'status',
        'processing',
        'processed',
        'duplicate_interaction',
        'failed',
        'first_interaction',
        'requested',
        'fail_reason',
    ];

    /* ---------- key helpers ---------- */

    public static function key(Order $order, string $suffix): string
    {
        return "order:{$order->getKey()}:{$suffix}";
    }

    private static function keys(Order $order): array
    {
        return array_map(
            fn (string $s) => self::key($order, $s),
            self::SUFFIXES
        );
    }

    /* ---------- read ---------- */

    public static function state(Order $order): OrderState
    {
        $raw = Cache::many(self::keys($order));

        return OrderState::fromRaw($order->getKey(), array_values($raw));
    }

    /* ---------- write ---------- */

    public static function processing(Order $order): int
    {
        return Cache::increment(self::key($order, 'processing'));
    }

    public static function processed(Order $order): int
    {
        return Cache::increment(self::key($order, 'processed'));
    }

    public static function failed(Order $order): int
    {
        return Cache::increment(self::key($order, 'failed'));
    }

    public static function failedWithReason(Order $order, ?string $reason): int
    {
        Cache::put(self::key($order, 'fail_reason'), $reason);

        return self::failed($order);
    }

    public static function duplicateInteraction(Order $order): int
    {
        return Cache::increment(self::key($order, 'duplicate_interaction'));
    }

    public static function setStatus(Order $order, string $status): void
    {
        Cache::put(self::key($order, 'status'), $status);
    }

    public static function setFirstInteraction(Order $order, int $timestamp): void
    {
        Cache::add(self::key($order, 'first_interaction'), $timestamp);
    }

    /* ---------- bulk helpers ---------- */

    public static function forget(Order $order, string $suffix): void
    {
        Cache::forget(self::key($order, $suffix));
    }

    public static function flush(Order $order): void
    {
        Cache::deleteMultiple(self::keys($order));
    }
}
