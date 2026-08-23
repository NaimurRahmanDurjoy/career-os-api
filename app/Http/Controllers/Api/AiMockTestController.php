<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiMockTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class AiMockTestController extends Controller
{
    /**
     * Display a listing of user's mock tests.
     */
    public function index()
    {
        $tests = AiMockTest::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($tests);
    }

    /**
     * Generate a new AI Mock Test for a given topic.
     */
    public function generate(Request $request)
    {
        if (Auth::user()->usage['mock_tests'] >= Auth::user()->limits['mock_tests']) {
            return response()->json([
                'success' => false,
                'message' => 'Monthly allowance reached for AI Mock Tests. Please upgrade to Pro for unlimited tests.',
                'requires_upgrade' => true
            ], 403);
        }

        $validated = $request->validate([
            'topic_name' => 'required|string|max:255',
        ]);

        try {
            $prompt = "You are an expert technical interviewer and examiner.
            Generate a 5-question multiple choice test for the following topic: '{$validated['topic_name']}'.
            The questions should be a mix of conceptual and practical knowledge.
            Return a JSON array of objects without markdown formatting. Each object must strictly have:
            - 'question': The question text
            - 'options': Array of 4 possible string answers
            - 'correctIndex': Integer (0-3) indicating the correct option
            - 'explanation': A brief explanation of why the answer is correct
            CRITICAL INSTRUCTION: Do NOT output a <think> block or reasoning trace. Bypass thinking completely and output ONLY the raw JSON array.";

            $response = OpenAI::chat()->create([
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5,
                'max_tokens' => 4000,
            ]);

            $jsonString = $response->choices[0]->message->content;
            $jsonString = preg_replace('/<think>.*?<\/think>\s*/s', '', $jsonString);
            $jsonString = preg_replace('/<think>.*$/s', '', $jsonString);
            
            // Robustly extract JSON (could be array or object container finding)
            if (preg_match('/\[[\s\S]*\]/', $jsonString, $matches)) {
                $jsonString = $matches[0];
            } else {
                $jsonString = trim(str_replace(['```json', '```'], '', $jsonString));
            }

            $parsed = json_decode($jsonString, true);
            $questions = [];
            
            if (!is_array($parsed)) {
                throw new Exception("AI returned unparseable JSON string: " . substr($jsonString, 0, 500) . "...");
            }
            
            if (isset($parsed['questions']) && is_array($parsed['questions'])) {
                $questions = $parsed['questions'];
            } elseif (isset($parsed[0])) {
                $questions = $parsed;
            } else {
                foreach ($parsed as $key => $val) {
                    if (is_array($val)) {
                        $questions = $val;
                        break;
                    }
                }
            }
            
            if (empty($questions)) {
                throw new Exception("Failed to parse AI response into questions array.");
            }

            $test = AiMockTest::create([
                'user_id' => Auth::id(),
                'topic_name' => $validated['topic_name'],
                'quiz_data' => $questions,
                'score' => 0,
            ]);

            return response()->json($test, 201);
        } catch (Exception $e) {
            \Log::error('Mock Test AI Gen Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate mock test: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific mock test.
     */
    public function show(string $id)
    {
        $test = AiMockTest::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($test);
    }

    /**
     * Submit answers to grade the test.
     */
    public function submit(Request $request, string $id)
    {
        $validated = $request->validate([
            'user_answers' => 'required|array',
        ]);

        $test = AiMockTest::where('user_id', Auth::id())->findOrFail($id);
        
        $answers = $validated['user_answers'];
        $quizData = $test->quiz_data;
        $score = 0;
        
        // Ensure accurate comparison 
        foreach ($quizData as $idx => $question) {
            if (isset($answers[$idx]) && $answers[$idx] == $question['correctIndex']) {
                $score += 100 / count($quizData);
            }
        }

        $test->update([
            'user_answers' => $answers,
            'score' => round($score)
        ]);

        return response()->json($test);
    }
    
    /**
     * Delete the test.
     */
    public function destroy(string $id)
    {
        $test = AiMockTest::where('user_id', Auth::id())->findOrFail($id);
        $test->delete();
        return response()->json(['success' => true]);
    }
}
