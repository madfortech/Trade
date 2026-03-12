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

    public function niftyAnalyze(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'message' => 'GROQ_API_KEY .env mein set nahi hai!'], 500);
            }

            $interval = $request->input('interval', '5m');
            $candles = $request->input('candles', []);
            $candleSummary = $this->candleAnalysis->buildCandleSummary($candles);

            $levels = $this->candleAnalysis->calcSupportResistance($candles);
            $supportStr = $this->candleAnalysis->formatLevel($levels['support'], 'N/A');
            $resistStr = $this->candleAnalysis->formatLevel($levels['resistance'], 'N/A');
            $currentPrice = $this->candleAnalysis->formatLevel($levels['current'], 'N/A');

            [$systemPrompt, $userPrompt] = $this->prompts->niftyAnalyze($interval, $currentPrice, $supportStr, $resistStr, $candleSummary);
            $data = $this->groq->callJson($systemPrompt, $userPrompt);

            $data['keyLevels'] = ['support' => $supportStr, 'resistance' => $resistStr];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::niftyAnalyze — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function niftyChat(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'reply' => '❌ GROQ_API_KEY .env mein set nahi hai!']);
            }

            $message = $request->input('message', '');
            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho!']);
            }

            $interval = $request->input('interval', '5m');
            $candles = $request->input('candles', []);
            $summary = $this->candleAnalysis->buildCandleSummary($candles);

            $levels = $this->candleAnalysis->calcSupportResistance($candles);
            $currentPrice = $this->candleAnalysis->formatLevel($levels['current']);
            $support = $this->candleAnalysis->formatLevel($levels['support']);
            $resistance = $this->candleAnalysis->formatLevel($levels['resistance']);

            [$systemPrompt, $userPrompt] = $this->prompts->niftyChat($interval, $currentPrice, $support, $resistance, $summary, $message);
            $reply = $this->groq->callText($systemPrompt, $userPrompt);

            return response()->json(['success' => true, 'reply' => $reply]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::niftyChat — ' . $e->getMessage());
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()]);
        }
    }

    public function analyze(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'message' => 'GROQ_API_KEY .env mein set nahi hai!'], 500);
            }

            $strike = $request->input('strike');
            $side = $request->input('side');
            $label = $request->input('label', 'NIFTY Option');
            $source = $request->input('source', 'Manual');
            $candles = $request->input('candles', []);
            $summary = $this->candleAnalysis->buildCandleSummary($candles);

            $levels = $this->candleAnalysis->calcSupportResistance($candles);
            $currentPrice = $this->candleAnalysis->formatLevel($levels['current']);
            $support = $this->candleAnalysis->formatLevel($levels['support']);
            $resistance = $this->candleAnalysis->formatLevel($levels['resistance']);

            [$systemPrompt, $userPrompt] = $this->prompts->optionAnalyze($label, $strike, $side, $source, $currentPrice, $support, $resistance, $summary);
            $data = $this->groq->callJson($systemPrompt, $userPrompt);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::analyze — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function chartChat(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'reply' => '❌ GROQ_API_KEY .env mein set nahi hai!']);
            }

            $message = $request->input('message', '');
            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho bhai!']);
            }

            $strike = $request->input('strike');
            $side = $request->input('side');
            $label = $request->input('label', 'NIFTY Option');
            $context = $request->input('context', []);
            $candles = $context['candles'] ?? [];
            $summary = $this->candleAnalysis->buildCandleSummary($candles);

            $levels = $this->candleAnalysis->calcSupportResistance($candles);
            $currentPrice = $this->candleAnalysis->formatLevel($levels['current']);
            $support = $this->candleAnalysis->formatLevel($levels['support']);
            $resistance = $this->candleAnalysis->formatLevel($levels['resistance']);

            [$systemPrompt, $userPrompt] = $this->prompts->optionChartChat($label, $strike, $side, $currentPrice, $support, $resistance, $summary, $message);
            $reply = $this->groq->callText($systemPrompt, $userPrompt);

            return response()->json(['success' => true, 'reply' => $reply]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::chartChat — ' . $e->getMessage());
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()]);
        }
    }

    public function sensexAnalyze(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'message' => 'GROQ_API_KEY .env mein set nahi hai!'], 500);
            }

            $label = $request->input('label', 'SENSEX Option');
            $strike = $request->input('strike');
            $side = $request->input('side');
            $candles = $request->input('candles', []);
            $summary = $this->candleAnalysis->buildCandleSummary($candles);
            $interval = $request->input('interval', '5m');
            $spot = $request->input('spot', '—');

            $levels = $this->candleAnalysis->calcSupportResistance($candles);
            $support = $this->candleAnalysis->formatLevel($levels['support'], 'N/A');
            $resistance = $this->candleAnalysis->formatLevel($levels['resistance'], 'N/A');

            [$systemPrompt, $userPrompt] = $this->prompts->sensexAnalyze($label, $strike, $side, $spot, $interval, $support, $resistance, $summary);
            $data = $this->groq->callJson($systemPrompt, $userPrompt);
            $data['keyLevels'] = ['support' => $support, 'resistance' => $resistance];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::sensexAnalyze — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function sensexChat(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'reply' => '❌ GROQ_API_KEY .env mein set nahi hai!']);
            }

            $message = $request->input('message', '');
            $history = $request->input('history', []);
            $context = $request->input('context', '');

            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho!']);
            }

            $systemPrompt = $this->prompts->sensexChatSystem($context);

            $messages = [];
            foreach (array_slice($history, -6) as $h) {
                if (in_array($h['role'] ?? '', ['user', 'assistant'])) {
                    $messages[] = ['role' => $h['role'], 'content' => $h['content']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            $reply = $this->groq->callMulti($systemPrompt, $messages);

            return response()->json(['success' => true, 'reply' => $reply]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::sensexChat — ' . $e->getMessage());
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()]);
        }
    }

    public function sensexChartChat(Request $request): JsonResponse
    {
        try {
            if (!$this->groq->hasApiKey()) {
                return response()->json(['success' => false, 'reply' => '❌ GROQ_API_KEY .env mein set nahi hai!']);
            }

            $message = $request->input('message', '');
            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho!']);
            }

            $label = $request->input('label', 'SENSEX Option');
            $strike = $request->input('strike', '—');
            $side = $request->input('side', '—');
            $context = $request->input('context', []);
            $candles = $context['candles'] ?? [];
            $summary = $this->candleAnalysis->buildCandleSummary($candles);

            $levels = $this->candleAnalysis->calcSupportResistance($candles);
            $currentPrice = $this->candleAnalysis->formatLevel($levels['current']);
            $support = $this->candleAnalysis->formatLevel($levels['support']);
            $resistance = $this->candleAnalysis->formatLevel($levels['resistance']);
            $spot = $context['spot'] ?? '—';
            $interval = $context['interval'] ?? '5m';

            [$systemPrompt, $userPrompt] = $this->prompts->sensexChartChat($label, $strike, $side, $spot, $currentPrice, $interval, $support, $resistance, $summary, $message);
            $reply = $this->groq->callText($systemPrompt, $userPrompt);

            return response()->json(['success' => true, 'reply' => $reply]);
        } catch (\Exception $e) {
            Log::error('AIAnalysis::sensexChartChat — ' . $e->getMessage());
            return response()->json(['success' => false, 'reply' => '❌ Error: ' . $e->getMessage()]);
        }
    }
}
