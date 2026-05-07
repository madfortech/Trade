<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;

class AngelLoginController extends Controller
{
    private $baseUrl = "https://apiconnect.angelone.in";

    public function login(Request $request)
    {
        $debugFile = storage_path('logs/angel_debug.txt');
        $this->fileLog($debugFile, "=== Angel Login Attempt @ " . now() . " ===");

        try {
            // ----------------------------------------------------------------
            // Read DIRECTLY from .env file
            // ----------------------------------------------------------------
            $envPath = base_path('.env');
            $envVars = $this->parseEnvFile($envPath);

            $clientCode = $envVars['ANGEL_CLIENT_CODE'] ?? null;
            $password   = $envVars['ANGEL_PASSWORD']    ?? null;
            $rawSecret  = $envVars['ANGEL_TOTP_SECRET'] ?? null;
            $apiKey     = $envVars['ANGEL_API_KEY']     ?? null;

            $this->fileLog($debugFile, "Client Code : " . ($clientCode ?? 'NULL'));
            $this->fileLog($debugFile, "API Key     : " . ($apiKey     ?? 'NULL'));
            $this->fileLog($debugFile, "Raw Secret  : [" . ($rawSecret  ?? 'NULL') . "]");

            // ----------------------------------------------------------------
            // Clean the secret
            // ----------------------------------------------------------------
            $secret = strtoupper(preg_replace('/\s+/', '', $rawSecret ?? ''));
            $this->fileLog($debugFile, "Cleaned Secret: [" . $secret . "] Length: " . strlen($secret));

            // ----------------------------------------------------------------
            // Validate
            // ----------------------------------------------------------------
            if (strlen($secret) < 16) {
                $msg = "TOTP secret too short: " . strlen($secret) . " chars. Need >= 16.";
                $this->fileLog($debugFile, "ERROR: " . $msg);

                return response()->json([
                    'error'         => $msg,
                    'secret_length' => strlen($secret),
                    'angel_keys'    => array_keys(array_filter(
                        $envVars,
                        fn($k) => str_starts_with($k, 'ANGEL_'),
                        ARRAY_FILTER_USE_KEY
                    )),
                ], 422);
            }

            // ----------------------------------------------------------------
            // Generate TOTP — try current + prev + next window
            // Angel One AG8001 = wrong TOTP, so we try all 3 windows
            // ----------------------------------------------------------------
            $google2fa = new Google2FA();
            $google2fa->setEnforceGoogleAuthenticatorCompatibility(false);

            $timestamp = $google2fa->getTimestamp();
            $window    = 1; // accept 1 period before and after (~30s each side)

            // Generate all 3 for logging
            $totpPrev    = $google2fa->oathTotp($secret, $timestamp - 1);
            $totpCurrent = $google2fa->oathTotp($secret, $timestamp);
            $totpNext    = $google2fa->oathTotp($secret, $timestamp + 1);

            $this->fileLog($debugFile, "Timestamp    : " . $timestamp);
            $this->fileLog($debugFile, "TOTP prev    : " . $totpPrev);
            $this->fileLog($debugFile, "TOTP current : " . $totpCurrent);
            $this->fileLog($debugFile, "TOTP next    : " . $totpNext);

            // Try each TOTP until one works
            $totpsToTry = [$totpCurrent, $totpPrev, $totpNext];
            $loginSuccess = false;
            $resData      = [];
            $usedTotp     = null;

            foreach ($totpsToTry as $totp) {
                $this->fileLog($debugFile, "Trying TOTP: " . $totp);

                $response = Http::withHeaders([
                    'Content-Type'     => 'application/json',
                    'Accept'           => 'application/json',
                    'X-UserType'       => 'USER',
                    'X-SourceID'       => 'WEB',
                    'X-ClientLocalIP'  => $request->ip(),
                    'X-ClientPublicIP' => $request->ip(),
                    'X-MACAddress'     => '00-00-00-00-00-00',
                    'X-PrivateKey'     => $apiKey,
                ])->post($this->baseUrl . "/rest/auth/angelbroking/user/v1/loginByPassword", [
                    'clientcode' => $clientCode,
                    'password'   => $password,
                    'totp'       => $totp,
                ]);

                $resData = $response->json();
                $this->fileLog($debugFile, "Response for $totp : " . json_encode($resData));

                if (!empty($resData['status']) && $resData['status'] === true) {
                    $loginSuccess = true;
                    $usedTotp     = $totp;
                    break;
                }

                // If not a TOTP error, no point retrying
                $errorCode = $resData['errorCode'] ?? '';
                if ($errorCode !== 'AG8001') {
                    $this->fileLog($debugFile, "Non-TOTP error ($errorCode), stopping retry.");
                    break;
                }

                // Wait 1 second before next attempt
                sleep(1);
            }

            // ----------------------------------------------------------------
            // Handle final result
            // ----------------------------------------------------------------
            if ($loginSuccess) {
                $request->session()->put('angel_jwt',  $resData['data']['jwtToken']);
                $request->session()->put('clientCode', $clientCode);
                $request->session()->regenerate();

                $this->fileLog($debugFile, "LOGIN SUCCESS with TOTP: " . $usedTotp);
                return redirect()->route('angel.home');
            }

            $this->fileLog($debugFile, "LOGIN FAILED — " . json_encode($resData));

            return response()->json([
                'error'      => 'Login Failed',
                'response'   => $resData,
                'totp_tried' => $totpsToTry,
            ], 401);

        } catch (\Exception $e) {
            $this->fileLog($debugFile, "EXCEPTION: " . $e->getMessage());
            $this->fileLog($debugFile, "TRACE: "     . $e->getTraceAsString());

            return response()->json([
                'error' => $e->getMessage(),
                'hint'  => 'Check storage/logs/angel_debug.txt',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Parse .env file directly
    // -------------------------------------------------------------------------
    private function parseEnvFile(string $path): array
    {
        $vars = [];

        if (!file_exists($path)) {
            return $vars;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $vars[$key] = $value;
        }

        return $vars;
    }

    // -------------------------------------------------------------------------
    // Plain file logger
    // -------------------------------------------------------------------------
    private function fileLog(string $path, string $message): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        try { Log::info($message); } catch (\Throwable $e) {}
    }
}