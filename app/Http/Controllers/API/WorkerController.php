<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $limit = (int) $request->get('limit', 4);
        $random = filter_var(
            $request->get('random', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $query = Worker::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($random) {
            $query->inRandomOrder();
        }

        $workers = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $workers,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Worker $worker): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $worker,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request): JsonResponse
    {
        $worker = Worker::query()->where('username', $request->username)->firstOrFail();
        $worker->fill($request->all());
        $worker->save();

        return response()->json([
            'success' => true,
            'data' => $worker->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function upsert(Request $request): JsonResponse
    {
        // Grab everything the client sent
        $data = $request->all();

        // Optional: prevent accidentally overriding primary key/timestamps
        unset($data['id'], $data['created_at'], $data['updated_at']);

        // updateOrCreate will use the model mutator for password
        $worker = Worker::updateOrCreate(
            ['username' => $data['username']],
            $data
        );

        return response()->json([
            'success' => true,
            'data' => $worker->fresh(),
        ], 200);
    }

    public function getExecutors(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 4);
        $f = 24 * 60;
        $l = 6 * 60;

        $workers = Worker::where('status', 'active')
            ->where(function ($q) use ($f) {
                $threshold = now()->subMinutes($f);
                $q->whereNull('warned_follow_at')
                    ->orWhere('warned_follow_at', '<', $threshold);
            })
            ->where(function ($q) use ($l) {
                $threshold = now()->subMinutes($l);
                $q->whereNull('warned_like_at')
                    ->orWhere('warned_like_at', '<', $threshold);
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workers,
        ], 200);
    }
}
