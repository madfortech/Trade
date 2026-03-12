<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  STANDALONE DEBUG FILE — Laravel se bilkul alag
 *  
 *  INSTRUCTIONS:
 *  1. Yeh file copy karo: public/debug_nifty.php
 *  2. Browser mein open karo: https://yoursite.com/debug_nifty.php
 *  3. Market hours mein test karo (9:15 - 15:30 IST)
 *  4. Output copy karke share karo
 *  5. TESTING KE BAAD DELETE KARO (security risk)
 * ══════════════════════════════════════════════════════════════
 */

// Laravel bootstrap — session + env access ke liye
// require __DIR__ . '/../vendor/autoload.php';
// $app = require_once __DIR__ . '/../bootstrap/app.php';
// $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = session('angel_jwt');

// ── Agar session se token nahi mila, .env se API key check karo ──────────────
$apiKey = env('ANGEL_API_KEY', 'NOT_SET');

echo "<pre style='font-family:monospace;font-size:13px;background:#0f172a;color:#e2e8f0;padding:20px;'>";
echo "═══════════════════════════════════════════════════\n";
echo "  NIFTY SPOT DEBUG — " . date('d-M-Y H:i:s') . " IST\n";
echo "═══════════════════════════════════════════════════\n\n";

echo "📋 ENVIRONMENT CHECK:\n";
echo "  ANGEL_API_KEY : " . ($apiKey !== 'NOT_SET' ? "✅ SET (" . substr($apiKey, 0, 4) . "****)" : "❌ NOT SET in .env") . "\n";
echo "  JWT in session: " . ($token ? "✅ PRESENT (" . strlen($token) . " chars)" : "❌ NOT FOUND — pehle login karo!") . "\n";

// IST time check
$ist = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$hour = (int)$ist->format('H');
$min  = (int)$ist->format('i');
$day  = (int)$ist->format('N'); // 1=Mon, 7=Sun
$hhmm = $hour * 100 + $min;
$marketOpen = ($day <= 5) && ($hhmm >= 915) && ($hhmm <= 1530);
echo "  IST time     : " . $ist->format('H:i:s d-M-Y') . "\n";
echo "  Market status: " . ($marketOpen ? "✅ OPEN" : "❌ CLOSED (spot API returns 0 when closed!)") . "\n\n";

if (!$token) {
    echo "⛔ JWT token nahi mila session mein.\n";
    echo "   Pehle browser mein /angel/login karo, phir yeh page kholo.\n";
    echo "</pre>";
    exit;
}

$baseUrl = "https://apiconnect.angelone.in";
$headers = [
    'Content-Type'     => 'application/json',
    'Accept'           => 'application/json',
    'X-UserType'       => 'USER',
    'X-SourceID'       => 'WEB',
    'X-PrivateKey'     => $apiKey,
    'X-ClientLocalIP'  => '127.0.0.1',
    'X-ClientPublicIP' => '127.0.0.1',
    'X-MACAddress'     => '00:00:00:00:00:00',
    'Authorization'    => 'Bearer ' . $token,
];

function callApi(string $url, array $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => array_map(fn($k,$v) => "$k: $v", array_keys($headers), $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['http' => $status, 'raw' => $raw, 'json' => json_decode($raw, true), 'curl_err' => $err];
}

// ════════════════════════════════════════════════════════
// TEST 1 — Quote API LTP Mode
// ════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 1: Quote API — LTP Mode (token 99926000)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$t1 = callApi("$baseUrl/rest/secure/angelbroking/market/v1/quote/", [
    'mode' => 'LTP', 'exchangeTokens' => ['NSE' => ['99926000']],
], $headers);

echo "  HTTP Status   : {$t1['http']}\n";
$j1 = $t1['json'] ?? [];
echo "  API status    : " . ($j1['status']  ?? 'null') . "\n";
echo "  API message   : " . ($j1['message'] ?? 'null') . "\n";
$ltp1 = $j1['data']['fetched'][0]['ltp'] ?? null;
echo "  LTP fetched   : " . ($ltp1 ? ($ltp1 > 10000 ? "✅ {$ltp1}" : "⚠️  WRONG VALUE: {$ltp1}") : "❌ NOT IN fetched[]") . "\n";
$unf1 = $j1['data']['unfetched'] ?? [];
if (!empty($unf1)) {
    echo "  ⚠️  UNFETCHED  : " . json_encode($unf1) . " ← TOKEN 99926000 REJECTED BY API!\n";
}
echo "  Full response : " . json_encode($j1, JSON_PRETTY_PRINT) . "\n\n";

// ════════════════════════════════════════════════════════
// TEST 2 — Quote API FULL Mode
// ════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 2: Quote API — FULL Mode (token 99926000)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$t2 = callApi("$baseUrl/rest/secure/angelbroking/market/v1/quote/", [
    'mode' => 'FULL', 'exchangeTokens' => ['NSE' => ['99926000']],
], $headers);
$j2   = $t2['json'] ?? [];
$ltp2 = $j2['data']['fetched'][0]['ltp'] ?? null;
echo "  HTTP Status   : {$t2['http']}\n";
echo "  LTP fetched   : " . ($ltp2 ? ($ltp2 > 10000 ? "✅ {$ltp2}" : "⚠️  WRONG: {$ltp2}") : "❌ NOT IN fetched[]") . "\n";
$unf2 = $j2['data']['unfetched'] ?? [];
if (!empty($unf2)) echo "  ⚠️  UNFETCHED  : " . json_encode($unf2) . "\n";
echo "  Full response : " . json_encode($j2, JSON_PRETTY_PRINT) . "\n\n";

// ════════════════════════════════════════════════════════
// TEST 3 — getLtpData (older endpoint)
// ════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 3: getLtpData endpoint\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$t3 = callApi("$baseUrl/rest/secure/angelbroking/order/v1/getLtpData", [
    'exchange' => 'NSE', 'tradingsymbol' => 'Nifty 50', 'symboltoken' => '99926000',
], $headers);
$j3   = $t3['json'] ?? [];
$ltp3 = $j3['data']['ltp'] ?? null;
echo "  HTTP Status   : {$t3['http']}\n";
echo "  API message   : " . ($j3['message'] ?? 'null') . "\n";
echo "  LTP           : " . ($ltp3 ? ($ltp3 > 10000 ? "✅ {$ltp3}" : "⚠️  WRONG: {$ltp3}") : "❌ NOT FOUND") . "\n";
echo "  Full response : " . json_encode($j3, JSON_PRETTY_PRINT) . "\n\n";

// ════════════════════════════════════════════════════════
// TEST 4 — NFO ATM options + Put-Call Parity
// ════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 4: NFO ATM Options + Put-Call Parity\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Scrip master se ATM tokens lo
$masterUrl = "https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json";
echo "  Fetching ScripMaster... ";
$masterJson = @file_get_contents($masterUrl);
$scrips     = $masterJson ? json_decode($masterJson, true) : null;

if (!is_array($scrips)) {
    echo "❌ FAILED to fetch ScripMaster\n\n";
} else {
    echo "✅ " . count($scrips) . " scrips loaded\n";

    // Get nearest expiry
    $today    = strtotime(date('Y-m-d'));
    $expiries = [];
    foreach ($scrips as $s) {
        if (($s['name'] ?? '') === 'NIFTY' && ($s['exch_seg'] ?? '') === 'NFO' && !empty($s['expiry'])) {
            if (strtotime($s['expiry']) >= $today) $expiries[] = $s['expiry'];
        }
    }
    $expiries = array_unique($expiries);
    usort($expiries, fn($a,$b) => strtotime($a)-strtotime($b));
    $nearestExpiry = $expiries[0] ?? null;
    echo "  Nearest expiry: {$nearestExpiry}\n";

    // Format expiry for symbol
    $fExp = strtoupper($nearestExpiry ?? '');
    if (strlen($fExp) > 7) $fExp = substr($fExp, 0, 5) . substr($fExp, -2);
    echo "  Formatted expiry (for symbol): {$fExp}\n";

    // Rough ATM using a common known range — 22000-25000
    // We'll find CE+PE pairs that exist and have non-zero LTP
    $tokenMap = [];
    foreach ($scrips as $s) {
        if (($s['exch_seg'] ?? '') === 'NFO' && ($s['name'] ?? '') === 'NIFTY') {
            $tokenMap[$s['symbol']] = $s['token'];
        }
    }

    // Try strikes 22000-25000 in 50 steps — pick 6 near assumed ATM 23000-24000
    $testStrikes = range(22500, 24500, 250);
    $testTokens  = [];
    $tokenSymMap = [];
    foreach ($testStrikes as $s) {
        foreach (['CE','PE'] as $t) {
            $sym = "NIFTY{$fExp}{$s}{$t}";
            if (isset($tokenMap[$sym])) {
                $testTokens[]       = $tokenMap[$sym];
                $tokenSymMap[$tokenMap[$sym]] = ['strike'=>$s,'type'=>$t,'sym'=>$sym];
            }
        }
    }

    echo "  Tokens found for test strikes: " . count($testTokens) . "\n";

    if (!empty($testTokens)) {
        $t4 = callApi("$baseUrl/rest/secure/angelbroking/market/v1/quote/", [
            'mode' => 'LTP', 'exchangeTokens' => ['NFO' => array_slice($testTokens, 0, 50)],
        ], $headers);
        $j4 = $t4['json'] ?? [];

        $ltpMap = [];
        foreach ($j4['data']['fetched'] ?? [] as $item) {
            $tok = $item['symbolToken'] ?? null;
            if ($tok && isset($tokenSymMap[$tok])) {
                $info          = $tokenSymMap[$tok];
                $ltpMap[$info['strike']][$info['type']] = (float)$item['ltp'];
            }
            // Also try by tradingSymbol
            if (preg_match('/(\d+)(CE|PE)$/', $item['tradingSymbol'] ?? '', $m)) {
                $ltpMap[(int)$m[1]][$m[2]] = (float)$item['ltp'];
            }
        }

        echo "\n  PUT-CALL PARITY RESULTS:\n";
        echo "  Strike    CE_LTP   PE_LTP   Derived_Spot\n";
        echo "  ─────────────────────────────────────────\n";

        $bestDiff = PHP_FLOAT_MAX;
        $bestSpot = null;
        $bestStrike = null;

        foreach ($ltpMap as $strike => $sides) {
            if (!isset($sides['CE'], $sides['PE'])) continue;
            $ce   = $sides['CE'];
            $pe   = $sides['PE'];
            $spot = round($strike + $ce - $pe, 2);
            $diff = abs($ce - $pe);
            $mark = $diff < $bestDiff ? " ← ATM" : "";
            if ($diff < $bestDiff) {
                $bestDiff   = $diff;
                $bestSpot   = $spot;
                $bestStrike = $strike;
            }
            echo "  {$strike}    {$ce}    {$pe}    {$spot}{$mark}\n";
        }

        if ($bestSpot) {
            echo "\n  ✅ DERIVED NIFTY SPOT: {$bestSpot} (ATM strike: {$bestStrike})\n";
        } else {
            echo "\n  ❌ Could not derive spot — no CE+PE pairs found\n";
            echo "  Fetched count   : " . count($j4['data']['fetched'] ?? []) . "\n";
            echo "  Unfetched count : " . count($j4['data']['unfetched'] ?? []) . "\n";
            echo "  Full NFO response: " . json_encode($j4, JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "  ❌ No matching tokens found in ScripMaster for expiry {$fExp}\n";
        echo "  Sample symbols tried: NIFTY{$fExp}23000CE, NIFTY{$fExp}23000PE ...\n";
        // Show sample of what symbols exist for this expiry
        $sample = 0;
        foreach ($scrips as $s) {
            if (($s['name']??'') === 'NIFTY' && ($s['exch_seg']??'') === 'NFO' && strpos($s['symbol'],$fExp) !== false) {
                echo "  Found symbol: {$s['symbol']} → token: {$s['token']}\n";
                if (++$sample >= 10) { echo "  ... (showing first 10)\n"; break; }
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// TEST 5 — JWT Token validity check
// ════════════════════════════════════════════════════════
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 5: JWT Token Validity\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
// Decode JWT payload (no verification — just inspect)
$parts = explode('.', $token);
if (count($parts) === 3) {
    $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);
    if ($payload) {
        $exp = $payload['exp'] ?? null;
        $iat = $payload['iat'] ?? null;
        echo "  Issued at  : " . ($iat ? date('d-M-Y H:i:s', $iat) . " IST" : "N/A") . "\n";
        echo "  Expires at : " . ($exp ? date('d-M-Y H:i:s', $exp) . " IST" : "N/A") . "\n";
        echo "  Status     : " . ($exp && $exp < time() ? "❌ EXPIRED — re-login karo!" : "✅ VALID") . "\n";
        echo "  Payload    : " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "  Could not decode JWT payload\n";
        echo "  Token (first 80 chars): " . substr($token, 0, 80) . "...\n";
    }
} else {
    echo "  Token format invalid (not a JWT)\n";
    echo "  Token value: " . substr($token, 0, 80) . "\n";
}

echo "\n═══════════════════════════════════════════════════\n";
echo "★  SUMMARY\n";
echo "═══════════════════════════════════════════════════\n";
echo "  T1 Quote LTP  : " . (isset($ltp1) && $ltp1 > 10000 ? "✅ {$ltp1}" : "❌ " . ($ltp1 ?? 'null')) . "\n";
echo "  T2 Quote FULL : " . (isset($ltp2) && $ltp2 > 10000 ? "✅ {$ltp2}" : "❌ " . ($ltp2 ?? 'null')) . "\n";
echo "  T3 getLtpData : " . (isset($ltp3) && $ltp3 > 10000 ? "✅ {$ltp3}" : "❌ " . ($ltp3 ?? 'null')) . "\n";
echo "  T4 Parity     : " . (isset($bestSpot) && $bestSpot ? "✅ {$bestSpot}" : "❌ failed") . "\n";
echo "  Market Open   : " . ($marketOpen ? "✅ YES" : "❌ NO (market band hai, spot 0 aayega)") . "\n";
echo "═══════════════════════════════════════════════════\n";
echo "\nYeh output copy karke share karo.\n";
echo "</pre>";
