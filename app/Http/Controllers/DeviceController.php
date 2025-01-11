<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class DeviceController extends Controller
{
    public function index()
    {
        $activeThreshold = Carbon::now()->subMinutes(30);

        $devices = Device::with('statistics')
            ->addSelect([
                '*',
                DB::raw("CASE
                WHEN last_activity >= NOW() - INTERVAL '30 minutes' THEN 'active'
                ELSE 'inactive'
            END as current_status"),
            ])
            ->orderBy('last_activity', 'desc')
            ->get();

        $activeWorkers = Device::where('mode', 'worker')
            ->where('last_activity', '>=', $activeThreshold)
            ->count();

        $activeLogins = Device::where('mode', 'login')
            ->where('last_activity', '>=', $activeThreshold)
            ->count();

        // Calculate total statistics
        $totalDevices = Device::count();
        $activeDevices = $activeWorkers + $activeLogins;
        $inactiveDevices = $totalDevices - $activeDevices;

        $workerVersion = Redis::get('system:worker:version') ?? 'Not set';

        return view('pages.devices', [
            'devices' => $devices,
            'stats' => [
                'all' => $totalDevices,
                'active' => $activeDevices,
                'dead' => $inactiveDevices,
                'worker' => $activeWorkers,
                'login' => $activeLogins,
            ],
            'workerVersion' => $workerVersion,
        ]);
    }

    public function updateMode(Request $request, Device $device)
    {
        $request->validate(['mode' => 'required|in:login,worker']);

        $device->mode = $request->mode;
        $device->save();

        // Update Redis cache
        Redis::set("device:mode:{$device->name}", $request->mode);

        return response()->json(['success' => true]);
    }
}
