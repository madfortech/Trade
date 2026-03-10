<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Faq;

class FaqController extends Controller
{

    public function index()
    {
        return view('faq');
    }
    

    public function edit(Faq $faq)
    {
        return view('admin.faq.edit', compact('faq'));
    }
    

    public function create()
    {
        return view('admin.faq.create');
    }
}
