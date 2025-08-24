<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemController extends Controller
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
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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

    public function addWorkerVersion(Request $request, string $version)
    {
        // 1. Authorize via header
        if ($request->header('Secret-Key') !== 'IndonesiaCemas') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2. Validate version format (optional but recommended)
        if (! preg_match('/^v\d{4}\.\d{2}\.\d{2}\.\d+$/', $version)) {
            return response()->json(['message' => 'Invalid version format'], 422);
        }

        // 3. Load existing versions from cache (default empty array)
        $versions = Cache::get('worker_versions', []);

        // 4. Add new version if not already present
        if (! in_array($version, $versions, true)) {
            $versions[] = $version;
            rsort($versions);                       // keep them ordered
            Cache::forever('system:worker_versions', $versions);
        }

        return response()->json([
            'message' => 'Version added',
            'version' => $version,
            'versions' => $versions,
        ], 201);
    }

    public function addWorkerVersion(Request $request, string $version)
    {
        // 1. Authorize via header
        if ($request->header('Secret-Key') !== 'IndonesiaCemas') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2. Validate version format (optional but recommended)
        if (! preg_match('/^v\d{4}\.\d{2}\.\d{2}\.\d+$/', $version)) {
            return response()->json(['message' => 'Invalid version format'], 422);
        }

        // 3. Load existing versions from cache (default empty array)
        $versions = Cache::get('worker_versions', []);

        // 4. Add new version if not already present
        if (! in_array($version, $versions, true)) {
            $versions[] = $version;
            rsort($versions);                       // keep them ordered
            Cache::forever('system:worker_versions', $versions);
        }

        return response()->json([
            'message' => 'Version added',
            'version' => $version,
            'versions' => $versions,
        ], 201);
    }
}
