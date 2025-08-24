<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

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

    public function addWorkerVersions(Request $request, string $version)
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
        $versions = Cache::get('system:worker_versions', []);

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

    public function setWorkerVersion(Request $request, string $version)
    {
        if ($request->header('Secret-Key') !== 'IndonesiaCemas') {
            return Response::json(['message' => 'Unauthorized'], 401);
        }

        // --- new validation ---
        $allowed = Cache::get('system:worker_versions', []);
        if (! in_array($version, $allowed, true)) {
            return Response::json(['message' => 'Version not found in allowed list'], 422);
        }
        // ----------------------

        Cache::forever('system:worker_version', $version);

        return Response::json(['message' => 'Version set', 'version' => $version], 200);
    }

    public function getWorkerVersion()
    {
        $versions = Cache::get('system:worker_versions', []);
        $version = Cache::get('system:worker_version');
        $serverVersion = Cache::get('system:server_version');

        $versions = array_slice($versions, -20, 20, true);

        return Response::json([
            'version' => $version,
            'versions' => $versions,
            'server-version' => $serverVersion,
        ]);
    }
}
