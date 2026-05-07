<?php

namespace App\Http\Controllers\Nifty;

use App\Http\Controllers\Controller;

class NiftyDashboardController extends Controller
{
    public function index()
    {
        return view('nifty.dashboard');
    }
}