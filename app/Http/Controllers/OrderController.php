<?php

namespace App\Http\Controllers;

use App\Client\BJSClient;
use App\Models\Order;
use App\Services\BJSService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    private $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService(new Order);
    }

    public function index(Request $request)
    {
        $this->orderService->updateCache();

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        // History orders query
        $historyQuery = Order::query()
            ->whereNotIn('status', ['processing', 'inprogress'])
            ->orderByDesc('id');

        // Apply search if provided
        if ($search) {
            $historyQuery->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhere('bjs_id', 'LIKE', "%{$search}%")
                    ->orWhere('reseller_name', 'LIKE', "%{$search}%")
                    ->orWhere('target', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%");
            });
        }

        // Paginate history results
        $history = $historyQuery->paginate($perPage)->withQueryString();

        $processeds = $this->orderService->getCachedOrders();

        // Enhance order data with Redis information
        $processeds = $processeds->map(function ($order) {
            $this->orderService->setOrderID($order->id);
            $redisData = $this->orderService->getOrderRedisKeys();

            $order->redis_status = $redisData['status'];
            $order->redis_processing = $redisData['processing'];
            $order->redis_processed = $redisData['processed'];
            $order->redis_failed = $redisData['failed'];
            $order->redis_duplicate = $redisData['duplicate_interaction'];
            $order->redis_requested = $redisData['requested'];

            return $order;
        });

        $orderCompleted = Order::query()->where('status', 'completed')->count();
        $orderProgress = Order::query()->where('status', 'inprogress')->count();
        $orderProcessing = Order::query()->where('status', 'processing')->count();
        $orderCanceled = Order::query()->where('status', 'canceled')->count();
        $orderPartialled = Order::query()->where('status', 'partial')->count();

        // Statistics
        $orderStats = Order::query()
            ->whereDate('created_at', now()->toDateString())
            ->select([
                'kind',
                DB::raw('COALESCE(SUM(requested), 0) as total_requested'),
                DB::raw('COALESCE(SUM(margin_request), 0) as total_margin_requested'),
            ])
            ->groupBy('kind')
            ->get();

        return view('pages.orders', [
            'processeds' => $processeds,
            'history' => $history,
            'search' => $search,
            // Order Section
            'orders' => [
                'completed' => $orderCompleted,
                'inprogress' => $orderProgress,
                'processing' => $orderProcessing,
                'canceled' => $orderCanceled,
                'partial' => $orderPartialled,
            ],
            // Statistics
            'statistics' => [
                'order' => $orderStats,
            ],
        ]);
    }

    public function store(Request $request)
    {
        // CfRQdvbBRT3
        $request->validate([
            'type' => 'required|in:follow,like',
            'target' => 'required|string',
            'requested' => 'required|integer|min:1',
        ]);

        $type = $request->type;
        $target = $request->target;
        $requested = $request->requested;
        $marginRequest = $requested + (ceil($requested * 0.1));

        $order = new Order;
        $order->source = 'direct';
        $order->status = 'processing';
        $order->status_bjs = 'processing';
        $order->target = $target;
        $order->kind = $type;
        $order->requested = $requested;
        $order->margin_request = $marginRequest;

        $bjsService = new BJSService(new BJSClient);
        $identifier = $bjsService->extractIdentifier($target);

        if ($type == 'follow') {
            try {
                // TODO: handle full URL
                $data = $this->getUserData($identifier);
                $order->username = $data['username'];
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to fetch user data: '.$e->getMessage());
            }
        } elseif ($type == 'like') {
            try {
                $data = $this->getDataMedia($identifier);
                $order->username = $data['owner_username'];
                $order->media_id = $data['pk'];
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to fetch media data: '.$e->getMessage());
            }
        }

        $order->save();

        // Create Redis keys
        Redis::set("order:{$order->id}:status", 'processing');
        Redis::set("order:{$order->id}:processing", 0);
        Redis::set("order:{$order->id}:processed", 0);
        Redis::set("order:{$order->id}:failed", 0);
        Redis::set("order:{$order->id}:duplicate_interaction", 0);
        Redis::set("order:{$order->id}:requested", $requested);

        // Update order cache
        $this->orderService->updateCache();

        return redirect()->route('orders.index')->with('success', 'Order created successfully');
    }

    private function getDataMedia($code)
    {
        $auth = config('app.redispo_auth');
        if ($auth === null) {
            throw new \Exception('Redispo auth not configured');
        }

        $response = Http::withHeaders([
            'authorization' => $auth,
        ])->get('http://172.104.183.180:12091/v2/proxy-ig/media-info-proxyv2', [
            'media_shortcode' => $code,
            'source' => 'belanjasosmed',
        ]);

        if (! $response->ok() || (bool) $response->json('error')) {
            throw new \Exception('Error response from server');
        }

        if (! $response->json('data.found')) {
            throw new \Exception('Media data is not foundd');
        }

        $media = $response->json('data.media');

        return [
            'pk' => $media['pk'],
            'owner_username' => $media['owner']['username'],
            'owner_pk_id' => $media['owner']['pk_id'],
            'owner_id' => $media['owner']['id'],
            'owner_is_private' => $media['owner']['is_private'],
            'like_and_view_counts_disabled' => $media['like_and_view_counts_disabled'],
            'comment_count' => $media['comment_count'],
            'like_count' => $media['like_count'],
        ];
    }

    private function getUserData($username)
    {
        $auth = config('app.redispo_auth');
        if ($auth === null) {
            return;
        }

        $req = Http::withHeaders([
            'authorization' => $auth,
        ])
            ->get("http://172.104.183.180:12091/v2/proxy-ig/search-by-username?username=$username");

        if (! $req->ok() || (bool) $req->json('error')) {
            throw new \Exception('Error response from server');
        }

        if (! $req->json('data.found') || ! (bool) $req->json('data.user')) {
            throw new \Exception('User data is not foundd');
        }

        $user = $req->json('data.user');
        $resp = [
            'error' => true,
            'username' => $username,
            'pk' => $user['pk'],
            'has_anonymous_profile_picture' => $user['has_anonymous_profile_picture'],
            'follower_count' => $user['follower_count'],
            'following_count' => $user['following_count'],
            'total_media_timeline' => $user['total_media_timeline'],
        ];

        return $resp;
    }

    public function incrementPriority(Order $order)
    {
        $order->increment('priority');
        $this->orderService->updateCache();

        return back()->with('success', 'Order priority increased');
    }

    public function decrementPriority(Order $order)
    {
        $order->decrement('priority');
        $this->orderService->updateCache();

        return back()->with('success', 'Order priority decreased');
    }

    public function destroy(Order $order)
    {
        if ($order->source === 'bjs' && ! (bool) Redis::get('system:bjs:login-state')) {
            return back()->with('error', 'Cannot delete BJS order because login state is false');
        }

        $order->delete();
        $this->orderService->deleteOrderRedisKeys($order->id);
        $this->orderService->updateCache();
        if ($order->source === 'bjs') {
            $cli = new BJSService(new BJSClient);
            $cli->auth();
            $cli->bjs->cancelOrder($order->bjs_id);
        }

        return back()->with('success', 'Order deleted successfully');
    }

    public function refill(Order $prevOrder)
    {
        $previousTarget = $prevOrder->requested + $prevOrder->start_count;

        $refill = new Order;
        $refill->source = 'refill';
        $refill->status = 'processing';
        $refill->status_bjs = 'processing';
        $refill->target = $prevOrder->target;
        $refill->kind = $prevOrder->kind;
        $refill->username = $prevOrder->username;
        $refill->media_id = $prevOrder->media_id;
        $refill->priority = 2;

        $bjsService = new BJSService(new BJSClient);
        $identifier = $bjsService->extractIdentifier($prevOrder->target);

        if ($prevOrder->kind == 'follow') {
            try {
                $data = $this->getUserData($identifier);
                $current = $data['follower_count'];

                if ($current >= $previousTarget) {
                    return back()
                        ->with('error', "Refill invalid: C still exceeding PT | PT: $previousTarget C: $current");
                }

                $refill->start_count = $current;
                $refill->requested = $current - $previousTarget;
                $refill->marginRequest = $current - $previousTarget;
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to fetch user data: '.$e->getMessage());
            }
        } elseif ($prevOrder->kind == 'like') {
            try {
                $data = $this->getDataMedia($identifier);
                $current = $data['like_count'];

                if ($current >= $previousTarget) {
                    return back()
                        ->with('error', "Refill invalid: C still exceeding PT | PT: $previousTarget C: $current");
                }

                $refill->start_count = $current;
                $refill->requested = $current - $previousTarget;
                $refill->marginRequest = $current - $previousTarget;
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to fetch media data: '.$e->getMessage());
            }
        }

        $refill->save();

        // Create Redis keys
        Redis::set("order:{$prevOrder->id}:status", 'processing');
        Redis::set("order:{$prevOrder->id}:processing", 0);
        Redis::set("order:{$prevOrder->id}:processed", 0);
        Redis::set("order:{$prevOrder->id}:failed", 0);
        Redis::set("order:{$prevOrder->id}:duplicate_interaction", 0);
        Redis::set("order:{$prevOrder->id}:requested", $refill->requested);

        // Update order cache
        $this->orderService->updateCache();

        return back()->with('success', 'Order is back to current process');
    }

    public function getInfo(Request $request): JsonResponse
    {
        $query = Order::query();

        // Validate request parameters
        if (! $request->has('id') && ! $request->has('bjs_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide either id or bjs_id',
            ], 400);
        }

        // Find order by ID or BJS ID
        if ($request->has('id')) {
            $order = $query->find($request->id);
        } else {
            $order = $query->where('bjs_id', $request->bjs_id)->first();
        }

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Get Redis state
        $this->orderService->setOrderID($order->id);
        $redisState = $this->orderService->getOrderRedisKeys();

        // Combine order data with Redis state
        $response = [
            'success' => true,
            'data' => [
                'order' => $order,
                'redis_state' => $redisState,
                'stats' => [
                    'progress_percentage' => $order->requested > 0 ?
                        round(($order->processed / $order->requested) * 100, 2) : 0,
                    'remaining' => max(0, $order->requested - $order->processed),
                    'is_completed' => $order->processed >= $order->requested,
                    'redis_processed' => (int) $redisState['processed'],
                    'redis_processing' => (int) $redisState['processing'],
                    'redis_failed' => (int) $redisState['failed'],
                    'redis_duplicate' => (int) $redisState['duplicate_interaction'],
                ],
            ],
        ];

        return response()->json($response);
    }
}
