<?php

namespace App\Http\Controllers;

use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function clearCache(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            DashboardCache::forgetAllForUser($user->id);
        }

        return back();
    }
}
