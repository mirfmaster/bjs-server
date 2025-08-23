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
        // 1.  Cached pending orders
        $likes = Cache::get('orders:pending:like', collect());
        $follows = Cache::get('orders:pending:follow', collect());

        // 2.  Keep only the ones that are actually processable
        $likes = $likes->filter(fn (Order $o) => OrderCache::processable($o));
        $follows = $follows->filter(fn (Order $o) => OrderCache::processable($o));

        // 3.  Random slice sizes
        $maxLike = random_int(
            (int) config('app.orders.like.min_per_worker', 4),
            (int) config('app.orders.like.max_per_worker', 30)
        );
        $maxFollow = random_int(
            (int) config('app.orders.follow.min_per_worker', 1),
            (int) config('app.orders.follow.max_per_worker', 3)
        );

        // 4.  Slice down to what this worker will handle
        $workerLikes = $likes->take($maxLike);
        $workerFollows = $follows->take($maxFollow);

        return response()->json([
            'orders' => [
                'totalLikes' => $likes->count(),
                'totalFollow' => $follows->count(),
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

    public function failed(Order $order)
    {
        $value = OrderCache::failed($order);

        return response()->json(['failed' => $value]);
    }

    public function setFailReason(Request $request, Order $order)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);
        $count = OrderCache::failReason($order, $request->input('reason'));

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
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        if (! empty($validated['reason'])) {
            OrderCache::failReason($order, $validated['reason']);
        }

        OrderCache::setStatus($order, $status->value);

        return response()->json([
            'status' => $status->value,
            'reason' => $request->reason,
        ]);
    }
}
