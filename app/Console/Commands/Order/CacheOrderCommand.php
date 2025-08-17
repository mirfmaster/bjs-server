<?php

namespace App\Console\Commands\Order;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheOrderCommand extends Command
{
    protected $signature = 'order:cache';

    protected $description = 'Cache pending orders, let worker take based on their config';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $likeBatch = (int) config('app.orders.like.batch_size', 10);
        $followBatch = (int) config('app.orders.follow.batch_size', 5);

        $likes = Order::query()
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'like')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($likeBatch)
            ->get();

        $follows = Order::query()
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'like')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($followBatch)
            ->get();

        Cache::forever('orders:pending:like', $likes);
        Cache::forever('orders:pending:follow', $follows);

        return Command::SUCCESS;
    }
}
