<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class ChatAssistantController extends Controller
{
    public function respond(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached.'], 403);
        }

        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:user,assistant,system',
            'messages.*.content' => 'required|string',
        ]);

        $resume = $request->user()->resumes()->where('is_primary', true)->first();
        
        $systemContext = "You are a highly experienced Executive Career Coach and AI assistant working for the CareerOS platform. 
Your goal is to help the user navigate their job search, provide interview strategies, and offer salary negotiation advice. 
Be highly concise, actionable, and encouraging. Use standard markdown formatting if helpful (bullet points, bolding). Avoid using generic AI filler phrases.";

        // COST OPTIMIZATION: Only inject the giant Resume JSON if the user's latest query actually explicitly relates to it.
        $latestUserMessage = end($validated['messages'])['content'] ?? '';
        $needsResumeContext = preg_match('/(resume|cv|experience|profile|background|skills|history)/i', $latestUserMessage);

        if ($resume && $needsResumeContext) {
            // Minify JSON to save tokens
            $systemContext .= "\n\nContext about the User: " . json_encode($resume->parsed_content['structured_data'] ?? []);
        }

        // COST OPTIMIZATION: Cap conversational history to the last 6 messages (3 interactions) instead of 15.
        $chatHistory = array_slice($validated['messages'], -6);

        $messages = array_merge([
            ['role' => 'system', 'content' => $systemContext]
        ], $chatHistory);

        try {
            $response = OpenAI::chat()->create([
                'model' => config('services.groq.model'),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ]);

            $tokensUsed = $response->usage->totalTokens ?? 0;

            $request->user()->aiUsageLogs()->create([
                'feature_name' => 'chatbot',
                'tokens_used' => $tokensUsed
            ]);

            $rawContent = $response->choices[0]->message->content;
            
            // Completely strip out any internal <think> reasoning blocks that advanced models output
            $cleanContent = trim(preg_replace('/<think>.*?<\/think>/s', '', $rawContent));

            return response()->json([
                'success' => true,
                'message' => [
                    'role' => 'assistant',
                    'content' => $cleanContent
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to generate response: ' . $e->getMessage()], 500);
        }
    }
}
