<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route; 
use Carbon\Carbon;
use App\Http\Controllers\AIAnalysisController;
use App\Http\Controllers\FaqController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-nifty', function () {
    return view('debug_nifty_spot');
});

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// Admin routes
Route::group(['middleware' => ['role:admin']], function () { 
    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::get('/faq/create', [FaqController::class, 'create'])->name('faq.create');
});


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});







require __DIR__.'/auth.php';

require __DIR__.'/angel.php';

