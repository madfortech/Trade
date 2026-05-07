<?php

namespace App\Http\Controllers;

use App\Services\AIAnalysis\CandleAnalysisService;
use App\Services\AIAnalysis\GroqChatService;
use App\Services\AIAnalysis\PromptFactory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIAnalysisController extends Controller
{
    public function __construct(
        private readonly CandleAnalysisService $candleAnalysis,
        private readonly GroqChatService $groq,
        private readonly PromptFactory $prompts,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | NIFTY ANALYZE
    |--------------------------------------------------------------------------
    */

    public function niftyAnalyze(Request $request): JsonResponse
    {
        try {

            if (!$this->groq->hasApiKey()) {

                return response()->json([
                    'success' => false,
                    'message' => 'GROQ API key missing'
                ], 500);
            }

            $interval = $request->input('interval', '5m');

            $candles = $request->input('candles', []);

            $summary =
                $this->candleAnalysis
                    ->buildCandleSummary($candles);

            $levels =
                $this->candleAnalysis
                    ->calcSupportResistance($candles);

            $support =
                $this->candleAnalysis
                    ->formatLevel($levels['support'], 'N/A');

            $resistance =
                $this->candleAnalysis
                    ->formatLevel($levels['resistance'], 'N/A');

            $currentPrice =
                $this->candleAnalysis
                    ->formatLevel($levels['current'], 'N/A');

            [$systemPrompt, $userPrompt] =
                $this->prompts
                    ->nifty()
                    ->analyze(
                        $interval,
                        $currentPrice,
                        $support,
                        $resistance,
                        $summary
                    );

            $data =
                $this->groq
                    ->callJson($systemPrompt, $userPrompt);

            $data['keyLevels'] = [
                'support' => $support,
                'resistance' => $resistance
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {

            Log::error(
                'AIAnalysis::niftyAnalyze — ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NIFTY CHAT
    |--------------------------------------------------------------------------
    */

    public function niftyChat(Request $request): JsonResponse
    {
        try {

            if (!$this->groq->hasApiKey()) {

                return response()->json([
                    'success' => false,
                    'reply' => 'GROQ API key missing'
                ]);
            }

            $message =
                trim($request->input('message', ''));

            if (empty($message)) {

                return response()->json([
                    'reply' => 'Ask something.'
                ]);
            }

            $interval =
                $request->input('interval', '5m');

            $candles =
                $request->input('candles', []);

            $summary =
                $this->candleAnalysis
                    ->buildCandleSummary($candles);

            $levels =
                $this->candleAnalysis
                    ->calcSupportResistance($candles);

            $currentPrice =
                $this->candleAnalysis
                    ->formatLevel($levels['current']);

            $support =
                $this->candleAnalysis
                    ->formatLevel($levels['support']);

            $resistance =
                $this->candleAnalysis
                    ->formatLevel($levels['resistance']);

            [$systemPrompt, $userPrompt] =
                $this->prompts
                    ->nifty()
                    ->chat(
                        $interval,
                        $currentPrice,
                        $support,
                        $resistance,
                        $summary,
                        $message
                    );

            $reply =
                $this->groq
                    ->callText($systemPrompt, $userPrompt);

            return response()->json([
                'success' => true,
                'reply' => $reply
            ]);

        } catch (\Exception $e) {

            Log::error(
                'AIAnalysis::niftyChat — ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'reply' => 'AI temporarily unavailable'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OPTION ANALYZE
    |--------------------------------------------------------------------------
    */

    public function optionAnalyze(Request $request): JsonResponse
    {
        try {

            $candles =
                $request->input('candles', []);

            $summary =
                $this->candleAnalysis
                    ->buildCandleSummary($candles);

            $levels =
                $this->candleAnalysis
                    ->calcSupportResistance($candles);

            $support =
                $this->candleAnalysis
                    ->formatLevel($levels['support']);

            $resistance =
                $this->candleAnalysis
                    ->formatLevel($levels['resistance']);

            $currentPrice =
                $this->candleAnalysis
                    ->formatLevel($levels['current']);

            [$systemPrompt, $userPrompt] =
                $this->prompts
                    ->option()
                    ->analyze(
                        $request->input('label', ''),
                        $request->input('strike'),
                        $request->input('side'),
                        $request->input('source', ''),
                        $currentPrice,
                        $support,
                        $resistance,
                        $summary
                    );

            $data =
                $this->groq
                    ->callJson($systemPrompt, $userPrompt);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {

            Log::error(
                'AIAnalysis::optionAnalyze — ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'AI analysis failed'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SENSEX ANALYZE
    |--------------------------------------------------------------------------
    */

    public function sensexAnalyze(Request $request): JsonResponse
    {
        try {

            $candles =
                $request->input('candles', []);

            $summary =
                $this->candleAnalysis
                    ->buildCandleSummary($candles);

            $levels =
                $this->candleAnalysis
                    ->calcSupportResistance($candles);

            $support =
                $this->candleAnalysis
                    ->formatLevel($levels['support']);

            $resistance =
                $this->candleAnalysis
                    ->formatLevel($levels['resistance']);

            $currentPrice =
                $this->candleAnalysis
                    ->formatLevel($levels['current']);

            [$systemPrompt, $userPrompt] =
                $this->prompts
                    ->sensex()
                    ->analyze(
                        $request->input('interval', '5m'),
                        $currentPrice,
                        $support,
                        $resistance,
                        $summary
                    );

            $data =
                $this->groq
                    ->callJson($systemPrompt, $userPrompt);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {

            Log::error(
                'AIAnalysis::sensexAnalyze — ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'AI analysis failed'
            ], 500);
        }
    }
}