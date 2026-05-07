<?php

namespace App\Services\AIAnalysis;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqChatService
{
    private string $apiKey;
    private string $model   = 'llama-3.3-70b-versatile';
    private string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        // config() use karo — env() production mein cache miss karta hai
        $this->apiKey = config('services.groq.api_key', env('GROQ_API_KEY', ''));
    }

    public function hasApiKey(): bool
    {
        return !empty(trim($this->apiKey));
    }

    public function callJson(string $systemPrompt, string $userPrompt): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post($this->baseUrl, [
                'model'           => $this->model,
                'max_tokens'      => 1024,
                'temperature'     => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        $body = $response->json();

        Log::info('Groq callJson', [
            'status'    => $response->status(),
            'has_error' => isset($body['error']),
            'has_data'  => isset($body['choices']),
        ]);

        if (isset($body['error'])) {
            throw new Exception('Groq API Error: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        }

        if ($response->status() !== 200) {
            throw new Exception('Groq HTTP Error: ' . $response->status() . ' — ' . $response->body());
        }

        $text  = $body['choices'][0]['message']['content'] ?? '';
        $clean = trim(preg_replace('/```json\s*|```\s*/i', '', $text));

        if (empty($clean)) {
            throw new Exception('Groq returned empty response.');
        }

        $data = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Groq JSON parse failed', ['raw' => $clean]);
            throw new Exception('JSON parse error: ' . json_last_error_msg());
        }

        return $data;
    }

    public function callText(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post($this->baseUrl, [
                'model'       => $this->model,
                'max_tokens'  => 512,
                'temperature' => 0.4,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        $body = $response->json();

        if (isset($body['error'])) {
            throw new Exception('Groq API Error: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        }

        if ($response->status() !== 200) {
            throw new Exception('Groq HTTP Error: ' . $response->status());
        }

        return $body['choices'][0]['message']['content'] ?? 'No response received.';
    }

    public function callMulti(string $systemPrompt, array $messages): string
    {
        $payload = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post($this->baseUrl, [
                'model'       => $this->model,
                'max_tokens'  => 512,
                'temperature' => 0.4,
                'messages'    => $payload,
            ]);

        $body = $response->json();

        if (isset($body['error'])) {
            throw new Exception('Groq API Error: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        }

        if ($response->status() !== 200) {
            throw new Exception('Groq HTTP Error: ' . $response->status());
        }

        return $body['choices'][0]['message']['content'] ?? 'No response received.';
    }
}
