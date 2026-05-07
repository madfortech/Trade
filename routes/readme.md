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

Route::get('/debug-strikes', function(\Illuminate\Http\Request $request) {
    $token = session('angel_jwt');
    if (!$token) return response()->json(['error' => 'Not logged in']);

    $expiry = $request->get('expiry', '17MAR2026');

    // ScripMaster fresh fetch (no cache)
    $scrips = json_decode(file_get_contents(
        "https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json"
    ), true) ?? [];

    // NIFTY NFO symbols for this expiry
    $matched = [];
    $sampleSymbols = [];
    foreach ($scrips as $s) {
        if (($s['name'] ?? '') !== 'NIFTY') continue;
        if (($s['exch_seg'] ?? '') !== 'NFO') continue;
        if (($s['expiry'] ?? '') !== $expiry) continue;
        $matched[] = [
            'symbol' => $s['symbol'],
            'token'  => $s['token'],
            'expiry' => $s['expiry'],
        ];
        if (count($sampleSymbols) < 5) {
            $sampleSymbols[] = $s['symbol'];
        }
    }

    // Spot price
    $apiKey = env('ANGEL_API_KEY');
    $headers = [
        'Content-Type' => 'application/json', 'Accept' => 'application/json',
        'X-UserType' => 'USER', 'X-SourceID' => 'WEB',
        'X-PrivateKey' => $apiKey, 'X-ClientLocalIP' => '127.0.0.1',
        'X-ClientPublicIP' => '127.0.0.1', 'X-MACAddress' => '00:00:00:00:00:00',
        'Authorization' => 'Bearer ' . $token,
    ];

    $spotResp = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders($headers)
        ->post('https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/', [
            'mode' => 'LTP', 'exchangeTokens' => ['NSE' => ['99926000']],
        ]);
    $spot = $spotResp->json()['data']['fetched'][0]['ltp'] ?? 0;
    $atm  = (int)(round($spot / 50) * 50);

    // What buildOptionSymbols would generate
    $generatedSymbols = [];
    for ($strike = $atm - 100; $strike <= $atm + 100; $strike += 50) {
        $generatedSymbols[] = "NIFTY{$expiry}{$strike}CE";
        $generatedSymbols[] = "NIFTY{$expiry}{$strike}PE";
    }

    // How many generated symbols exist in scrip master
    $symbolMap = array_column($matched, 'token', 'symbol');
    $foundCount = 0;
    $notFound = [];
    foreach ($generatedSymbols as $gs) {
        if (isset($symbolMap[$gs])) {
            $foundCount++;
        } else {
            $notFound[] = $gs;
        }
    }

    return response()->json([
        'expiry'              => $expiry,
        'nifty_spot'          => $spot,
        'atm'                 => $atm,
        'total_in_scrip'      => count($matched),
        'sample_scrip_symbols'=> $sampleSymbols,
        'generated_sample'    => array_slice($generatedSymbols, 0, 6),
        'found_in_map'        => $foundCount,
        'not_found_sample'    => array_slice($notFound, 0, 4),
    ], 200, [], JSON_PRETTY_PRINT);
});


require __DIR__.'/auth.php';

require __DIR__.'/angel.php';


require __DIR__.'/nifty.php';

require __DIR__.'/sensex.php';

require __DIR__.'/option.php';