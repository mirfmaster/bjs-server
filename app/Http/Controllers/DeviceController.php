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
        $limitInactive = Carbon::now()->subMinutes(30);
        $devices = Device::with('statistics')
            ->addSelect([
                '*',
                DB::raw('CASE
                WHEN last_activity >= NOW() - INTERVAL \'30 minutes\' THEN \'active\'
                ELSE \'inactive\'
            END as current_status'),
            ])
            ->orderBy('last_activity', 'desc')
            ->get();
        // Count devices by mode
        $workerModeCount = Device::where('mode', 'worker')
            ->where('last_activity', '>=', $limitInactive)
            ->count();
        $loginModeCount = Device::where('mode', 'login')
            ->where('last_activity', '>=', $limitInactive)
            ->count();

        // Get all devices and count their states
        $deviceQuery = Device::query();

        $totalDevices = $deviceQuery->count();
        $inactiveDevices = $deviceQuery->where('last_activity', '<', $limitInactive)->count();
        $activeDevices = $totalDevices - $inactiveDevices;

        $workerVersion = Redis::get('system:worker:version') ?? 'Not set';

        return view('pages.devices', [
            'devices' => $devices,
            // Devices Section
            'stats' => [
                'all' => $totalDevices,
                'active' => $activeDevices,
                'dead' => $inactiveDevices,
                'worker' => $workerModeCount,
                'login' => $loginModeCount,
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
