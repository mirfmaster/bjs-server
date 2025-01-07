<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class DeviceController extends Controller
{
    public function index()
    {
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

        return view('pages.devices', [
            'devices' => $devices,
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
