<?php

namespace App\Http\Controllers\API;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // 1.  How many orders the server reserved (cached)
        // Triggered from command order:cache
        $likes = Cache::get('orders:pending:like', collect());
        $follows = Cache::get('orders:pending:follow', collect());

        // 2.  Random amount each worker may take
        $maxLike = random_int(
            (int) config('app.orders.like.min_per_worker', 10),
            (int) config('app.orders.like.max_per_worker', 30)
        );

        $maxFollow = random_int(
            (int) config('app.orders.follow.min_per_worker', 5),
            (int) config('app.orders.follow.max_per_worker', 20)
        );

        // 3.  Slice down to what the worker may actually process
        $workerLikes = $likes->take($maxLike);
        $workerFollows = $follows->take($maxFollow);

        return response()->json([
            'orders' => [
                'totalLikes' => count($likes),
                'totalFollow' => count($follows),
                'like' => $workerLikes,
                'follow' => $workerFollows,
            ],
            'config' => config('app.orders', []),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): JsonResponse
    {
        $order = Order::query() // adjust relations as needed
            ->findOrFail($id);

        $cache = OrderCache::state($order);

        return response()->json([
            'order' => $order,
            'state' => $cache,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    // --- STATE ------
    public function processing(Order $order)
    {
        $value = OrderCache::processing($order);

        return response()->json(['processing' => $value]);
    }

    public function processed(Order $order)
    {
        $value = OrderCache::processed($order);

        return response()->json(['processed' => $value]);
    }

    public function failed(Request $request, Order $order)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);
        $count = OrderCache::failedWithReason($order, $request->input('reason'));

        return response()->json(['failed' => $count, 'reason' => $request->input('reason')]);
    }

    public function duplicate(Order $order)
    {
        $value = OrderCache::duplicateInteraction($order);

        return response()->json(['duplicate_interaction' => $value]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $status = $request->enum('status', OrderStatus::class);
        OrderCache::setStatus($order, $status->value);

        return response()->json(['status' => $status->value]);
    }
}
