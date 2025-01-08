<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;

class SystemController extends Controller
{
    public function toggleBJSLoginState()
    {
        $currentState = (bool) Redis::get('system:bjs:login-state');
        $newState = ! $currentState;
        Redis::set('system:bjs:login-state', $newState);

        return redirect()->back();
    }
}
