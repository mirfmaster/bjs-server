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
}
