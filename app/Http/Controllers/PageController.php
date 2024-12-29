<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    /**
     * Display all the static pages when authenticated
     *
     * @return \Illuminate\View\View
     */
    public function index(string $page)
    {
        if (view()->exists("pages.{$page}")) {
            return view("pages.{$page}");
        }

        return abort(404);
    }

    // TODO: remove unused fn
    public function vr()
    {
        return view('pages.virtual-reality');
    }

    public function rtl()
    {
        return view('pages.rtl');
    }

    public function profile()
    {
        return view('pages.profile-static');
    }

    public function signin()
    {
        return view('pages.sign-in-static');
    }

    public function signup()
    {
        return view('pages.sign-up-static');
    }

    public function workers()
    {
        $statusCounts = Worker::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByRaw("array_position(
                ARRAY['active', 'relogin', 'new_login']
            , status), status")
            ->get();

        return view('pages.workers', [
            'statusCounts' => $statusCounts,
        ]);
    }

    public function apiDocs()
    {
        $apis = [
            [
                'method' => 'GET',
                'endpoint' => '/api/order/info',
                'description' => 'Get detailed order information including Redis state',
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => 'optional*', 'description' => 'Order ID'],
                    ['name' => 'bjs_id', 'type' => 'integer', 'required' => 'optional*', 'description' => 'BJS Order ID'],
                ],
                'notes' => '* Either id or bjs_id must be provided',
                'response_example' => json_encode([
                    'success' => true,
                    'data' => [
                        'order' => [
                            'id' => 123,
                            'bjs_id' => 456,
                            'requested' => 1000,
                            'processed' => 500,
                        ],
                        'redis_state' => [
                            'status' => 'processing',
                            'processed' => 500,
                        ],
                        'stats' => [
                            'progress_percentage' => 50.0,
                            'remaining' => 500,
                        ],
                    ],
                ], JSON_PRETTY_PRINT),
            ],
            [
                'method' => 'GET',
                'endpoint' => '/api/worker/info',
                'description' => 'Get worker information',
                'parameters' => [
                    ['name' => 'username', 'type' => 'string', 'required' => 'optional*', 'description' => 'Worker username'],
                    ['name' => 'id', 'type' => 'integer', 'required' => 'optional*', 'description' => 'Worker ID'],
                ],
                'notes' => '* Either username or id must be provided',
                'response_example' => json_encode([
                    'success' => true,
                    'data' => [
                        'username' => 'worker1',
                        'status' => 'active',
                        'followers_count' => 100,
                        'following_count' => 50,
                    ],
                ], JSON_PRETTY_PRINT),
            ],
        ];

        return view('pages.api-docs', compact('apis'));
    }
}
