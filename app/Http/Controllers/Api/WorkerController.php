<?php

namespace App\Http\Controllers\Api;

use App\Consts\OrderConst;
use App\Http\Controllers\Controller;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WorkerController extends Controller
{
    public function index()
    {
        // Get worker counts by status
        $statusCounts = Worker::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByRaw("array_position(ARRAY['active', 'relogin', 'new_login'], status), status")
            ->get();

        $workerCounters = Worker::query()->count();

        // Calculate new workers from indofoll
        $dailyNewWorkers = $this->getNewWorkerCount('daily');
        $weeklyNewWorkers = $this->getNewWorkerCount('weekly');
        $monthlyNewWorkers = $this->getNewWorkerCount('monthly');

        // Get worker statistics
        $activeWorkers = $statusCounts->where('status', 'active')->first()->count ?? 0;
        $activeMaxFollow = $this->getActiveWorkersWithMaxFollow();

        // Get 2FA statistics
        $twoFactorsAll = Worker::whereNotNull('secret_key_2fa')->count();
        $twoFactorsActive = Worker::whereNotNull('secret_key_2fa')->where('status', 'active')->count();

        // Get worker limitations
        $followLimit = OrderConst::TASK_LIMIT_ACCOUNT['follow'];
        $likeLimit = OrderConst::TASK_LIMIT_ACCOUNT['like'];
        $activeLimited = $this->getActiveLimitedWorkers($followLimit, $likeLimit);

        // Get daily statistics
        $statisticsDaily = $this->getDailyStatistics();

        // Get worker locks count
        $followLocks = count(Redis::keys('worker:*:lock-follow')) ?? 0;
        $likeLocks = count(Redis::keys('worker:*:lock-like')) ?? 0;

        return view('pages.workers', [
            'total' => $workerCounters,
            'statusCounts' => $statusCounts,
            'newWorkers' => [
                'daily' => $dailyNewWorkers,
                'weekly' => $weeklyNewWorkers,
                'monthly' => $monthlyNewWorkers,
            ],
            'twoFactors' => [
                'all' => $twoFactorsAll,
                'active' => $twoFactorsActive,
            ],
            'activeMaxFollow' => $activeMaxFollow,
            'locks' => [
                'follow' => $followLocks,
                'like' => $likeLocks,
            ],
            'statistics' => [
                'active' => $activeWorkers - $activeLimited,
                'active-limited' => $activeLimited,
                'total_follows' => $statisticsDaily->total_follows ?? 0,
                'total_likes' => $statisticsDaily->total_likes ?? 0,
            ],
        ]);
    }

    /**
     * Get count of new workers based on time period
     *
     * @param  string  $period  'daily', 'weekly', or 'monthly'
     * @return int Count of new workers
     */
    private function getNewWorkerCount(string $period): int
    {
        $query = Worker::query()->where('code', 'bjs:indofoll-job');

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
                break;
            case 'monthly':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]);
                break;
        }

        return $query->count();
    }

    /**
     * Get count of active workers with max follow error
     *
     * @return int Count of workers
     */
    private function getActiveWorkersWithMaxFollow(): int
    {
        return Worker::query()
            ->where('status', 'active')
            ->where('is_max_following_error', true)
            ->count();
    }

    /**
     * Get count of active workers who have reached daily limits
     *
     * @param  int  $followLimit  Daily follow limit
     * @param  int  $likeLimit  Daily like limit
     * @return int Count of limited workers
     */
    private function getActiveLimitedWorkers(int $followLimit, int $likeLimit): int
    {
        return Worker::query()
            ->where('status', 'active')
            ->whereRaw("(
            COALESCE((statistics->'follow'->>'daily')::integer, 0) > ? OR
            COALESCE((statistics->'like'->>'daily')::integer, 0) > ?
        )", [$followLimit, $likeLimit])
            ->count();
    }

    /**
     * Get aggregated daily statistics for active workers
     *
     * @return object Daily statistics totals
     */
    private function getDailyStatistics()
    {
        $results = DB::select("
        SELECT
            SUM(COALESCE((statistics->'follow'->>'daily')::integer, 0)) as total_follows,
            SUM(COALESCE((statistics->'like'->>'daily')::integer, 0)) as total_likes
        FROM workers
        WHERE status = 'active'
    ");

        return $results[0] ?? (object) ['total_follows' => 0, 'total_likes' => 0];
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
                'message' => 'Failed to update worker status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'delimiter' => 'sometimes|string', // Common delimiters
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Store the uploaded file
            $file = $request->file('csv_file');
            $filename = 'workers_' . date('Y_m_d_His') . '.csv';
            $path = $file->storeAs('assets/prod', $filename);

            if (! $path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store the uploaded file.',
                ], 500);
            }

            // Get delimiter from request or default to pipe
            $delimiter = $request->input('delimiter', '|');

            // Run the import command
            $exitCode = Artisan::call('workers:import', [
                'file' => storage_path('app/' . $path),
                '--delimiter' => $delimiter,
            ]);

            // Get command output
            $output = Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Workers imported successfully',
                    'details' => $output,
                ]);
            }

            // Clean up the file if import failed
            Storage::delete($path);

            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'details' => $output,
            ], 500);
        } catch (\Exception $e) {
            // Clean up file if exists
            if (isset($path)) {
                Storage::delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error processing file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLatestVersion()
    {
        $latest = Redis::get('system:worker:latest-version');
        $version = Redis::get('system:worker:version');

        return response()->json([
            'latest-version' => $latest,
            'version' => $version,
        ]);
    }
}
