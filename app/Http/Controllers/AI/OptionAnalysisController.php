<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AIAnalysis\CandleAnalysisService;
use App\Services\AIAnalysis\GroqChatService;
use App\Services\AIAnalysis\PromptFactory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OptionAnalysisController extends Controller
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
                    ->option()
                    ->analyze(
                        $request->input('label', ''),
                        $request->input('strike'),
                        $request->input('side'),
                        $request->input('source', ''),
                        $this->candleAnalysis->formatLevel($levels['current']),
                        $this->candleAnalysis->formatLevel($levels['support']),
                        $this->candleAnalysis->formatLevel($levels['resistance']),
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
                'Option AI Analyze Error: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'AI analysis failed'
            ], 500);
        }
    }
}