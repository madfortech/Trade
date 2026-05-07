<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\FaqController;

/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/

Route::get('/', function () {

    return view('welcome');

});

Route::get('/contact', function () {

    return view('contact');

})->name('contact');

Route::get(

    '/faq',

    [FaqController::class, 'index']

)->name('faq');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::middleware([

    'role:admin'

])->group(function () {

    Route::get(

        '/admin',

        [AdminController::class, 'index']

    )->name('admin');

    Route::get(

        '/faq/create',

        [FaqController::class, 'create']

    )->name('faq.create');
});

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {

    return view('dashboard');

})->middleware([

    'auth',
    'verified'

])->name('dashboard');

/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get(

        '/profile',

        [ProfileController::class, 'edit']

    )->name('profile.edit');

    Route::patch(

        '/profile',

        [ProfileController::class, 'update']

    )->name('profile.update');

    Route::delete(

        '/profile',

        [ProfileController::class, 'destroy']

    )->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| ROUTE FILES
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';

require __DIR__ . '/angel.php';

require __DIR__ . '/nifty.php';

require __DIR__ . '/sensex.php';

require __DIR__ . '/option.php';

