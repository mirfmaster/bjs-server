<?php

namespace App\Http\Controllers;

use App\Client\BJSClient;
use App\Client\InstagramClient;
use App\Models\Order;
use App\Services\BJSService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    private $orderService;

    public function __construct(public BJSService $bjsService)
    {
        $this->orderService = new OrderService(new Order());
    }

    public function index(Request $request)
    {
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
        $cachedOrders = Cache::getMultiple(['order:list:like', 'order:list:follow'], collect([]));
        $processeds = collect(Arr::collapse($cachedOrders))
            ->transform(function (Order $order) {
                $id = $order->id;

                $raw = Cache::tags("order:$id")->many([
                    "order:{$id}:status",
                    "order:{$id}:processing",
                    "order:{$id}:processed",
                    "order:{$id}:failed",
                    "order:{$id}:duplicate_interaction",
                    "order:{$id}:requested",
                    "order:{$id}:fail_reason",
                ]);

                // normalize them to ['status'=>..., 'processing'=>..., …]
                $state = collect($raw)
                    ->mapWithKeys(function ($value, $fullKey) {
                        $parts = explode(':', $fullKey);
                        $key = end($parts);

                        return [$key => $value];
                    });

                // dynamically add a `state` property to the Order model
                $order->state = $state;

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

        $leftOrders = Order::query()
            // ->whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['inprogress', 'processing'])
            ->select([
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(requested), 0) as total_requested'),
            ])
            ->first();

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
                'leftOrders' => $leftOrders,
            ],
            'system' => [
                'counterFollow' => Cache::get('system:follow_counter', 0),
                'counterLike' => Cache::get('system:like_counter', 0),
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

        $order = new Order();
        $order->source = 'direct';
        $order->status = 'processing';
        $order->status_bjs = 'processing';
        $order->target = $target;
        $order->kind = $type;
        $order->requested = $requested;
        $order->priority = 3;
        $order->margin_request = $marginRequest;

        $igCli = new InstagramClient();
        $identifier = $this->bjsService->extractIdentifier($target);
        if (! $this->bjsService->auth()) {
            return back()->with('error', 'BJS Cli auth failed, please retry');
        }

        if ($type == 'follow') {
            try {
                // TODO: handle full URL
                $data = $igCli->fetchProfile($identifier);
                if (! $data->found) {
                    return back()->with('error', 'Target is not found');
                }
                $order->username = $data->username;
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to fetch user data: ' . $e->getMessage());
            }
        } elseif ($type == 'like') {
            try {
                $data = $this->getDataMedia($identifier);
                $order->username = $data['owner_username'];
                $order->media_id = $data['pk'];
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to fetch media data: ' . $e->getMessage());
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
            'owner_username' => $media['user']['username'],
            'owner_pk_id' => $media['user']['pk_id'],
            'owner_id' => $media['user']['id'],
            'owner_is_private' => $media['user']['is_private'],
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
        try {
            if ($order->source === 'bjs' && ! (bool) Redis::get('system:bjs:login-state')) {
                return back()->with('error', 'Cannot delete BJS order because login state is false');
            }

            $this->orderService->setOrderID($order->id);
            $newStatus = $this->orderService->evaluateOrderStatus();

            $string = '';
            if ($order->source === 'bjs') {
                $cli = new BJSService(new BJSClient());
                $resp = $cli->auth();
                if (! $resp) {
                    return back()->with('error', 'BJS Cli auth failed, please retry');
                }

                if ($newStatus === 'cancel') {
                    $updateReq = $cli->bjs->cancelOrder($order->bjs_id);
                    $string .= 'StatusUpdate: ' . ($updateReq ? 'TRUE' : 'FALSE');
                } elseif ($newStatus === 'partial') {
                    $remainingCount = $this->orderService->getRemains();
                    $remainingUpdated = $cli->bjs->setRemains($order->bjs_id, $remainingCount);
                    $string .= 'RemainUpdate: ' . ($remainingUpdated ? 'TRUE' : 'FALSE');

                    $updateReq = $cli->bjs->changeStatus($order->bjs_id, $newStatus);
                    $string .= 'StatusUpdate: ' . ($updateReq ? 'TRUE' : 'FALSE');
                }
            }

            $order->update([
                'status' => $newStatus,
                'status_bjs' => $newStatus,
            ]);
            $this->orderService->setStatusRedis($newStatus);
            $this->orderService->updateCache();

            return back()->with('success', 'Order deleted successfully. ' . $string);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete order: ' . $e->getMessage());
        }
    }

    public function refill(Order $order)
    {
        $previousTarget = $order->requested + $order->start_count;
        $identifier = $this->bjsService->extractIdentifier($order->target);
        if (! $this->bjsService->auth()) {
            return back()->with('error', 'BJS Cli auth failed, please retry');
        }

        try {
            $currentData = $this->getCurrentData($order->kind, $identifier);
            $current = $currentData['count'];

            if ($current >= $previousTarget) {
                return back()->with(
                    'error',
                    "Refill invalid: C still exceeding PT | PT: $previousTarget C: $current"
                );
            }

            $requestedAmount = $previousTarget - $current;

            $refillData = [
                'source' => 'refill',
                'status' => 'inprogress',
                'status_bjs' => 'inprogress',
                'target' => $order->target,
                'kind' => $order->kind,
                'username' => $order->username,
                'media_id' => $order->media_id,
                'priority' => 2,
                'start_count' => $current,
                'requested' => $requestedAmount,
                'margin_request' => $requestedAmount,
            ];

            $this->orderService->createAndUpdateCache($refillData);

            return back()->with('success', 'Order is back to current process with status refill');
        } catch (\Exception $e) {
            return back()->with('error', "Failed to fetch {$order->kind} data: " . $e->getMessage());
        }
    }

    private function getCurrentData(string $type, string $identifier): array
    {
        if ($type === 'follow') {
            $data = $this->getUserData($identifier);

            return ['count' => $data['follower_count']];
        }

        $data = $this->getDataMedia($identifier);

        return ['count' => $data['like_count']];
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
