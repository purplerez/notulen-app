<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(Request $request): View
    {
        return view('dashboard', [
            'user' => $request->user(),
            'organization' => $request->user()->organization,
        ]);
    }
}
