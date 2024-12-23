<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;

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
