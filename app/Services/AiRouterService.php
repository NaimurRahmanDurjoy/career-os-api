<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;
use OpenAI\Laravel\Facades\OpenAI;

class AiRouterService
{
    /**
     * Executes an AI generation prompt automatically routed to the cheapest/best provider or user BYOK.
     * 
     * @param string $prompt The prompt/request context
     * @param string $taskType 'fast_json', 'heavy_reasoning', or 'creative_writing'
     * @param int $maxTokens Default 8000
     * @return string Raw generated text
     */
    public function executePrompt(string $prompt, string $taskType = 'fast_json', int $maxTokens = 8000): string
    {
        $user = auth()->user() ?? request()->user();
        
        // 1. BYOK CHECK (Highest Priority - Saves Platform Costs)
        if ($user && !empty($user->custom_api_keys['openai'])) {
            try {
                return $this->callOpenAITraditional($prompt, $user->custom_api_keys['openai'], $maxTokens);
            } catch (Exception $e) {
                \Log::warning('User custom OpenAI key failed, falling back: ' . $e->getMessage());
            }
        }
        if ($user && !empty($user->custom_api_keys['gemini'])) {
            try {
                return $this->callGemini($prompt, $user->custom_api_keys['gemini'], $maxTokens);
            } catch (Exception $e) {
                \Log::warning('User custom Gemini key failed, falling back: ' . $e->getMessage());
            }
        }
        if ($user && !empty($user->custom_api_keys['groq'])) {
            try {
                return $this->callGroq($prompt, $maxTokens, $user->custom_api_keys['groq']);
            } catch (Exception $e) {
                \Log::warning('User custom Groq key failed, falling back: ' . $e->getMessage());
            }
        }

        // 2. FALLBACK SMART ROUTING
        if ($taskType === 'fast_json' || $taskType === 'heavy_reasoning') {
            try {
                // Gemini Flash handles massive context and structured JSON instantly.
                return $this->callGemini($prompt, env('GEMINI_API_KEY'), $maxTokens);
            } catch (Exception $e) {
                \Log::warning('System Gemini API failed, falling back to Groq: ' . $e->getMessage());
                return $this->callGroq($prompt, $maxTokens);
            }
        }

        // Default Groq for basic writing operations.
        return $this->callGroq($prompt, $maxTokens);
    }

    private function callGemini(string $prompt, string $apiKey, int $maxTokens): string
    {
        $response = Http::timeout(60)->withHeaders([
            'Content-Type' => 'application/json'
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $apiKey, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => min($maxTokens, 8192), // Clamp array length to save Google quotas
            ]
        ]);

        if (!$response->successful()) {
            throw new Exception("Gemini API Connection failed: " . $response->body());
        }

        $rawOutput = $response->json('candidates.0.content.parts.0.text') ?? '';
        return trim(preg_replace('/<think>.*?(<\/think>|$)/is', '', $rawOutput));
    }

    private function callGroq(string $prompt, int $maxTokens, ?string $apiKey = null): string
    {
        if ($apiKey) {
            // If the user supplied their own Groq Key, route natively via cURL instead of Laravel Facade config
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => "Bearer " . $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
                'max_tokens' => $maxTokens
            ]);
            
            if (!$response->successful()) throw new Exception("Groq API failed: " . $response->body());
            $rawOutput = $response->json('choices.0.message.content') ?? '';
        } else {
            // Using platform master key
            $response = OpenAI::chat()->create([
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
                'max_tokens' => $maxTokens,
            ]);
            $rawOutput = $response->choices[0]->message->content ?? '';
        }

        return trim(preg_replace('/<think>.*?(<\/think>|$)/is', '', $rawOutput));
    }

    private function callOpenAITraditional(string $prompt, string $apiKey, int $maxTokens): string
    {
        $response = Http::timeout(60)->withHeaders([
            'Authorization' => "Bearer " . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.4,
            'max_tokens' => $maxTokens
        ]);

        if (!$response->successful()) {
            throw new Exception("OpenAI API Connection failed: " . $response->body());
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }
}
