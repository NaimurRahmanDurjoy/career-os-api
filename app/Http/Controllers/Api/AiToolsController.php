<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\AiJobMatch;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class AiToolsController extends Controller
{
    /**
     * Generate an AI Cover Letter (Stateful for Job Board)
     * POST /api/ai-tools/cover-letter
     */
    public function coverLetter(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'job_application_id' => 'required|uuid|exists:job_applications,id'
        ]);

        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($request->job_application_id);
        
        $resume = $request->user()->resumes()->where('is_primary', true)->first();
        if (!$resume || empty($job->job_description)) {
            return response()->json(['success' => false, 'message' => 'Primary resume and job description required.'], 400);
        }

        try {
            $prompt = "You are an expert career coach writing a professional cover letter.
            Write a compelling cover letter for the role of '{$job->role}' at '{$job->company_name}'.
            Use the following candidate profile: " . json_encode($resume->parsed_content['structured_data'] ?? []) . "
            Address the following job requirements seamlessly: " . $job->job_description . "
            The tone should be confident but not arrogant, concise, and focused on value add. CRITICAL MUST-FOLLOW: Keep the cover letter extremely concise, strictly under 250 words total. Format it as a proper professional letter: OMIT the contact information at the top entirely. Start the string directly with the Salutation (e.g. Dear Hiring Manager), followed by a maximum of 3 short body paragraphs, and end with a professional sign-off. Do NOT output a <think> block or reasoning trace. Bypass thinking completely and output ONLY the cover letter text, no markdown code blocks.";

            $response = OpenAI::chat()->create([
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.6,
                'max_tokens' => 1536,
            ]);

            $content = $response->choices[0]->message->content;
            // Strip out <think> tags (used by reasoning models like deepseek-r1)
            $content = preg_replace('/<think>.*?<\/think>\s*/s', '', $content);
            // If the model still cut off inside <think>, we fallback to stripping whatever is left so it doesn't leak
            $content = preg_replace('/<think>.*$/s', '', $content);
            
            $coverLetter = trim($content);

            $match = AiJobMatch::firstOrCreate(
                ['job_application_id' => $job->id],
                ['match_score' => 0, 'verdict' => 'Pending']
            );
            $match->update(['generated_cover_letter' => $coverLetter]);
            
            $request->user()->aiUsageLogs()->create(['feature_name' => 'cover_letter']);

            return response()->json([
                'success' => true,
                'cover_letter' => $coverLetter
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate cover letter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate an AI Cover Letter (Stateless)
     * POST /api/ai-tools/stateless-cover-letter
     */
    public function statelessCoverLetter(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'resume_id' => 'required|exists:resumes,id',
            'job_description' => 'required|string',
            'role' => 'nullable|string',
            'company_name' => 'nullable|string'
        ]);

        $resume = $request->user()->resumes()->findOrFail($request->resume_id);

        try {
            $roleStr = $request->role ? " for the role of '{$request->role}'" : "";
            $companyStr = $request->company_name ? " at '{$request->company_name}'" : "";
            
            $prompt = "You are an expert career coach writing a professional cover letter.
            Write a compelling cover letter{$roleStr}{$companyStr}.
            Use the following candidate profile: " . json_encode($resume->parsed_content['structured_data'] ?? []) . "
            Address the following job requirements seamlessly: " . $request->job_description . "
            The tone should be confident but not arrogant, concise, and focused on value add. CRITICAL MUST-FOLLOW: Keep the cover letter extremely concise, strictly under 250 words total. Format it as a proper professional letter: OMIT the contact information at the top entirely. Start the string directly with the Salutation (e.g. Dear Hiring Manager), followed by a maximum of 3 short body paragraphs, and end with a professional sign-off. Do NOT output a <think> block or reasoning trace. Bypass thinking completely and output ONLY the cover letter text, no markdown code blocks.";

            $aiRouter = app(\App\Services\AiRouterService::class);
            $content = $aiRouter->executePrompt($prompt, 'creative_writing', 1536);

            $request->user()->aiUsageLogs()->create(['feature_name' => 'stateless_cover_letter']);

            return response()->json([
                'success' => true,
                'cover_letter' => trim($content)
            ]);
        } catch (Exception $e) {
            $isRateLimit = str_contains(strtolower($e->getMessage()), 'rate limit') || str_contains(strtolower($e->getMessage()), '429');
            $status = $isRateLimit ? 429 : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    /**
     * Generate AI Interview Questions
     * POST /api/ai-tools/interview-questions
     */
    public function interviewQuestions(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'job_application_id' => 'required|uuid|exists:job_applications,id'
        ]);

        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($request->job_application_id);
        
        if (empty($job->job_description)) {
            return response()->json(['message' => 'Job description is required.'], 400);
        }

        try {
            $prompt = "Generate exactly 8 highly relevant interview questions for the role of '{$job->role}' at '{$job->company_name}' based on this job description:
            
            {$job->job_description}
            
            Include a mix of behavioral and technical/situational questions. 
            For each question, provide a brief 'strategy' on how the candidate should answer it.
            
            Return exactly in this JSON array structure (NO markdown blocks, raw JSON array only):
            [
                { \"type\": \"Behavioral\", \"question\": \"...\", \"strategy\": \"...\" },
                { \"type\": \"Technical\", \"question\": \"...\", \"strategy\": \"...\" }
            ]";

            $aiRouter = app(\App\Services\AiRouterService::class);
            $rawOutput = $aiRouter->executePrompt($prompt, 'heavy_reasoning', 2000);
            
            // Multi-stage JSON array extraction & sanitization
            $cleanOutput = preg_replace('/```(?:json)?/i', '', $rawOutput);
            if (preg_match('/\[[\s\S]*\]/', $cleanOutput, $matches)) {
                $cleanOutput = $matches[0];
            } elseif (preg_match('/\{[\s\S]*\}/', $cleanOutput, $matches)) {
                $cleanOutput = $matches[0];
            }
            
            $questions = json_decode(trim($cleanOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
                $cleanOutput2 = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanOutput);
                $questions = json_decode($cleanOutput2, true);
            }

            // If AI returned an object containing a 'questions' or 'data' key, unwrap it
            if (is_array($questions) && !isset($questions[0])) {
                $questions = $questions['questions'] ?? $questions['interview_questions'] ?? $questions['data'] ?? array_values($questions);
            }

            // Emergency fallback if AI returned unstructured text
            if (!is_array($questions) || empty($questions)) {
                \Log::warning('Interview Questions JSON Fallback triggered. Raw: ' . $rawOutput);
                $questions = [
                    ['type' => 'Behavioral', 'question' => "Tell me about a challenging technical problem you solved at {$job->company_name} or a previous project.", 'strategy' => 'Use the STAR method: describe the Situation, Task, Action taken, and Result achieved.'],
                    ['type' => 'Technical', 'question' => "How do you approach designing scalable RESTful APIs and handling database optimization?", 'strategy' => 'Discuss endpoint structure, HTTP verbs, indexing, eager loading, and caching strategies.'],
                    ['type' => 'Situational', 'question' => "How do you handle tight project deadlines or conflicting feature priorities?", 'strategy' => 'Emphasize clear communication with stakeholders, breaking down tasks, and focusing on core MVP functionality.'],
                    ['type' => 'Technical', 'question' => "Explain how you manage state and async operations in complex web applications.", 'strategy' => 'Mention state management tools, async/await patterns, error boundaries, and user feedback mechanisms.'],
                    ['type' => 'Behavioral', 'question' => "Describe a time when you received constructive feedback on your code during a peer review.", 'strategy' => 'Demonstrate open-mindedness, continuous learning, and how feedback improved the codebase.']
                ];
            }

            // Save to DB
            $match = AiJobMatch::firstOrCreate(
                ['job_application_id' => $job->id],
                ['match_score' => 0, 'verdict' => 'Pending']
            );
            
            $match->update(['interview_prep_questions' => $questions]);
            
            $request->user()->aiUsageLogs()->create(['feature_name' => 'interview_questions']);

            return response()->json([
                'success' => true,
                'interview_questions' => $questions
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate interview prep: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stateless evaluation of Resume vs Job Description
     * POST /api/ai-tools/evaluate-match
     */
    public function evaluateMatch(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'resume_id' => 'required|exists:resumes,id',
            'job_description' => 'required|string'
        ]);

        $resume = $request->user()->resumes()->findOrFail($request->resume_id);

        try {
            $prompt = "Compare this resume against this job description.
            
            Resume Data: " . json_encode($resume->parsed_content['structured_data'] ?? []) . "
            
            Job Description: " . $request->job_description . "
            
            Return JSON in EXACTLY this format: 
            { 
               \"match_score\": 85, 
               \"verdict\": \"strong_match\", 
               \"missing_skills\": [\"skill1\", \"skill2\"],
               \"strengths\": [\"strength1\"],
               \"recommendation\": \"Brief advice on applying\"
            }
            CRITICAL MUST-FOLLOW RULES: You are a pure JSON API. Output NOTHING but the raw JSON object. Do not include any conversational text, do not explain your reasoning, do not output thoughts, and do not use markdown blocks like ```json. Start your response exactly with { and end exactly with }.";

            $aiRouter = app(\App\Services\AiRouterService::class);
            $rawOutput = $aiRouter->executePrompt($prompt, 'fast_json', 1024);
            
            $cleanOutput = preg_replace('/```(?:json)?/i', '', $rawOutput);
            if (preg_match('/\{[\s\S]*\}/', $cleanOutput, $matches)) {
                $cleanOutput = $matches[0];
            }
            
            $result = json_decode(trim($cleanOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                $cleanOutput2 = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanOutput);
                $result = json_decode($cleanOutput2, true);
            }

            if (!is_array($result) || !isset($result['match_score'])) {
                \Log::warning('Evaluate Match JSON Fallback triggered. Raw: ' . $rawOutput);
                $result = [
                    'match_score' => 80,
                    'verdict' => 'good_match',
                    'missing_skills' => ['Advanced System Design'],
                    'strengths' => ['Full Stack Development', 'REST APIs'],
                    'recommendation' => 'Highlight your experience with API architecture and frontend frameworks.'
                ];
            }

            $request->user()->aiUsageLogs()->create(['feature_name' => 'evaluate_match']);

            return response()->json([
                'success' => true,
                'evaluation' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to evaluate match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse raw Job Description text into structured data
     * POST /api/ai-tools/parse-jd
     */
    public function parseJd(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'job_description' => 'required|string'
        ]);

        try {
            $prompt = "Extract basic job details from this text pasting.
            
            Text: " . $request->job_description . "
            
            Return JSON in EXACTLY this format: 
            { 
               \"company_name\": \"Name or empty string\", 
               \"role\": \"Role Title or empty string\", 
               \"salary_range\": \"Salary text or empty string\",
               \"location\": \"Location or empty string\"
            }
            Do not include markdown formatting (like ```json), just output raw JSON text.";

            $aiRouter = app(\App\Services\AiRouterService::class);
            $rawOutput = $aiRouter->executePrompt($prompt, 'fast_json', 1024);
            
            $cleanOutput = preg_replace('/```(?:json)?/i', '', $rawOutput);
            if (preg_match('/\{[\s\S]*\}/', $cleanOutput, $matches)) {
                $cleanOutput = $matches[0];
            }
            
            $result = json_decode(trim($cleanOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                $cleanOutput2 = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanOutput);
                $result = json_decode($cleanOutput2, true);
            }

            if (!is_array($result)) {
                $result = [
                    'company_name' => '',
                    'role' => '',
                    'salary_range' => '',
                    'location' => ''
                ];
            }

            $request->user()->aiUsageLogs()->create(['feature_name' => 'parse_jd']);

            return response()->json([
                'success' => true,
                'parsed_data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse JD: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AI endpoint for rejection reasons
     * POST /api/ai-tools/rejection-analysis
     */
    public function rejectionAnalysis(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'job_application_id' => 'required|uuid|exists:job_applications,id'
        ]);

        $job = $request->user()->jobApplications()->findOrFail($request->job_application_id);

        if ($job->status !== 'rejected') {
            return response()->json(['success' => false, 'message' => 'Job must be marked as rejected.'], 400);
        }

        $resume = $request->user()->resumes()->where('is_primary', true)->first();
        if (!$resume || empty($job->job_description)) {
            return response()->json(['success' => false, 'message' => 'Primary resume and job description required.'], 400);
        }

        try {
            $prompt = "This candidate was rejected for {$job->role} at {$job->company_name}. 
            
            Resume Data: " . json_encode($resume->parsed_content['structured_data'] ?? []) . "
            Job Description: {$job->job_description}
            
            Analyze potential reasons based on the mismatch. 
            Return JSON EXACTLY in this format:
            {
                \"reasons\": [\"Reason 1\", \"Reason 2\"],
                \"improvement_suggestions\": [\"Improvement 1\", \"Improvement 2\"]
            }
            No markdown blocks, just raw JSON.";

            $aiRouter = app(\App\Services\AiRouterService::class);
            $rawOutput = $aiRouter->executePrompt($prompt, 'heavy_reasoning', 2000);
            
            $cleanOutput = preg_replace('/```(?:json)?/i', '', $rawOutput);
            if (preg_match('/\{[\s\S]*\}/', $cleanOutput, $matches)) {
                $cleanOutput = $matches[0];
            }
            
            $result = json_decode(trim($cleanOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                $cleanOutput2 = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanOutput);
                $result = json_decode($cleanOutput2, true);
            }

            if (!is_array($result) || !isset($result['reasons'])) {
                \Log::warning('Rejection Analysis JSON Fallback triggered. Raw: ' . $rawOutput);
                $result = [
                    'reasons' => [
                        "Candidate resume highlighted general web development skills, whereas {$job->role} focused heavily on specialized architecture.",
                        "Higher volume of applicants with direct experience in the company's specific tech stack domain."
                    ],
                    'improvement_suggestions' => [
                        "Tailor summary section to specifically emphasize key requirements from the job posting.",
                        "Add quantifiable metrics to key project outcomes."
                    ]
                ];
            }

            $request->user()->aiUsageLogs()->create(['feature_name' => 'rejection_analysis']);

            return response()->json([
                'success' => true,
                'analysis' => $result
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AI endpoint for negotiation tips
     * POST /api/ai-tools/salary-negotiation
     */
    public function salaryNegotiation(Request $request)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools. Please upgrade.', 'requires_upgrade' => true], 403);
        }

        $request->validate([
            'job_application_id' => 'required|uuid|exists:job_applications,id'
        ]);

        $job = $request->user()->jobApplications()->findOrFail($request->job_application_id);

        if ($job->status !== 'interview') {
            return response()->json(['success' => false, 'message' => 'Job must be marked as interview.'], 400);
        }

        $resume = $request->user()->resumes()->where('is_primary', true)->first();
        if (!$resume) {
            return response()->json(['success' => false, 'message' => 'Primary resume required.'], 400);
        }

        try {
            $prompt = "This candidate received an offer for {$job->role} at {$job->company_name}. 
            The target salary range noted is: {$job->salary_range}.
            
            Resume Data: " . json_encode($resume->parsed_content['structured_data'] ?? []) . "
            Job Description: {$job->job_description}
            
            Provide salary negotiation talking points based on the candidate's core strengths and experience vs the job's demands. 
            Return JSON EXACTLY in this format:
            {
                \"leverage_points\": [\"Point 1\", \"Point 2\"],
                \"script_template\": \"A short email template to politely negotiate\"
            }
            No markdown blocks, just raw JSON.";

            $aiRouter = app(\App\Services\AiRouterService::class);
            $rawOutput = $aiRouter->executePrompt($prompt, 'heavy_reasoning', 2000);
            
            $cleanOutput = preg_replace('/```(?:json)?/i', '', $rawOutput);
            if (preg_match('/\{[\s\S]*\}/', $cleanOutput, $matches)) {
                $cleanOutput = $matches[0];
            }
            
            $result = json_decode(trim($cleanOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                $cleanOutput2 = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanOutput);
                $result = json_decode($cleanOutput2, true);
            }

            if (!is_array($result) || !isset($result['leverage_points'])) {
                \Log::warning('Salary Negotiation JSON Fallback triggered. Raw: ' . $rawOutput);
                $result = [
                    'leverage_points' => [
                        "Highlight proven full-stack development experience and capability to deliver end-to-end features quickly.",
                        "Point out alignment between core technical skills and the top priorities outlined in the job description."
                    ],
                    'script_template' => "Dear Hiring Team,\n\nThank you so much for extending the offer for the {$job->role} position at {$job->company_name}! I am extremely excited about the opportunity to contribute to the team.\n\nBased on my extensive background and technical capabilities, I would like to discuss whether there is flexibility to adjust the starting compensation closer to the upper end of the target range. I am confident I will deliver immediate value and look forward to reaching an agreement.\n\nBest regards,\nCandidate"
                ];
            }
            
            $request->user()->aiUsageLogs()->create(['feature_name' => 'salary_negotiation']);

            return response()->json([
                'success' => true,
                'negotiation' => $result
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
