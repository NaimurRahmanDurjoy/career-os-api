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
            - 'explanation': A brief explanation of why the answer is correct";

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5,
                'response_format' => ['type' => 'json_object'],
            ]);

            $jsonString = trim($response->choices[0]->message->content);
            // sometimes the model nests the array inside another key in json mode
            $parsed = json_decode($jsonString, true);
            $questions = [];
            
            if (isset($parsed['questions']) && is_array($parsed['questions'])) {
                $questions = $parsed['questions'];
            } elseif (is_array($parsed) && isset($parsed[0])) {
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
