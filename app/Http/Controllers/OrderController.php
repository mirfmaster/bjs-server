<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    private $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService(new Order());
    }

    public function index()
    {
        $processeds = $this->orderService->getCachedOrders();
        $outSync = $this->orderService->getOutOfSyncOrders();

        return view('pages.orders', [
            'processeds' => $processeds,
            'out_sync' => $outSync,
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
        $order->margin_request = $marginRequest;

        if ($type == 'follow') {
            $order->username = $target;
        } elseif ($type == 'like') {
            try {
                $data = $this->getDataMedia($target);
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
        ];
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
        $order->delete();
        $this->orderService->updateCache();

        return back()->with('success', 'Order deleted successfully');
    }
}
