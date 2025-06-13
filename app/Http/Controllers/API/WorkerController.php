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
    public function index()
    {
        //
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
    public function update(Request $request, Worker $worker): JsonResponse
    {
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

    public function getRelogin(Request $request)
    {
        $limit = $request->get('limit', 4);
    }
}
