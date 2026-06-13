<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AIAnalysis\CandleAnalysisService;
use App\Services\AIAnalysis\GroqChatService;
use App\Services\AIAnalysis\PromptFactory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NiftyAnalysisController extends Controller
{
    public function __construct(
        private readonly CandleAnalysisService $candleAnalysis,
        private readonly GroqChatService $groq,
        private readonly PromptFactory $prompts,
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        try {

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
                    ->niftyAnalyze(
                        $interval,
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
                'Nifty AI Analyze Error: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function chat(Request $request): JsonResponse
    {
        try {

            $message =
                trim($request->input('message', ''));

            if (!$message) {

                return response()->json([
                    'success' => false,
                    'reply' => 'Message missing'
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

           [$systemPrompt, $userPrompt] =
                $this->prompts
                    ->niftyChat(
                        $interval,
                        $this->candleAnalysis->formatLevel($levels['current']),
                        $this->candleAnalysis->formatLevel($levels['support']),
                        $this->candleAnalysis->formatLevel($levels['resistance']),
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
                'Nifty AI Chat Error: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'reply' => 'AI temporarily unavailable'
            ]);
        }
    }
}