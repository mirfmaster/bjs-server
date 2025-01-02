<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
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
}
