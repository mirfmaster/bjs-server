<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WorkerController extends Controller
{
    public function index()
    {
        $statusCounts = Worker::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByRaw("array_position(
                ARRAY['active', 'relogin', 'new_login']
            , status), status")
            ->get();
        $workerCounters = Worker::query()->count();
        $newWorkers = Worker::query()
            ->where('status', 'new_login')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $activeMaxFollow = Worker::query()
            ->where('status', 'active')
            ->where('is_max_following_error', true)
            ->count();

        return view('pages.workers', [
            'total' => $workerCounters,
            'statusCounts' => $statusCounts,
            'newWorkers' => $newWorkers,
            'activeMaxFollow' => $activeMaxFollow,
            'locks' => [
                'follow' => count(Redis::keys('worker:*:lock-follow')) ?? 0,
                'like' => count(Redis::keys('worker:*:lock-like')) ?? 0,
            ],
        ]);
    }

    public function getInfo(Request $request): JsonResponse
    {
        $query = Worker::query();

        if ($request->has('username')) {
            $worker = $query->where('username', $request->username)->first();
        } elseif ($request->has('id')) {
            $worker = $query->find($request->id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Please provide either username or id',
            ], 400);
        }

        if (! $worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $worker,
        ]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'from_status' => 'required|string',
            'to_status' => 'required|string',
            'limit' => 'nullable|integer|min:1',
        ]);

        try {
            $query = Worker::where('status', $request->query('from_status'));

            if ($request->has('limit')) {
                $query->limit($request->query('limit'));
            }

            $affectedRows = $query->update(['status' => $request->query('to_status')]);

            return response()->json([
                'success' => true,
                'data' => [
                    'affected_rows' => $affectedRows,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update worker status: '.$e->getMessage(),
            ], 500);
        }
    }
}
