<?php

namespace App\Http\Controllers\Nifty;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class NiftyExpiryController extends Controller
{
    public function change(Request $request)
    {
        return redirect()->back();
    }
}