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
            The tone should be confident but not arrogant, concise, and focused on value add. Return ONLY the cover letter text, no markdown code blocks.";

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5,
            ]);

            $coverLetter = trim($response->choices[0]->message->content);

            $match = AiJobMatch::firstOrCreate(
                ['job_application_id' => $job->id],
                ['match_score' => 0, 'verdict' => 'Pending']
            );
            $match->update(['generated_cover_letter' => $coverLetter]);

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
            The tone should be confident but not arrogant, concise, and focused on value add. Return ONLY the cover letter text, no markdown code blocks.";

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5,
            ]);

            return response()->json([
                'success' => true,
                'cover_letter' => trim($response->choices[0]->message->content)
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate AI Interview Questions
     * POST /api/ai-tools/interview-questions
     */
    public function interviewQuestions(Request $request)
    {
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

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ]);

            $rawOutput = $response->choices[0]->message->content;
            $rawOutput = str_replace(['```json', '```'], '', $rawOutput);
            
            $questions = json_decode(trim($rawOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
                throw new Exception("AI returned invalid structure.");
            }

            // Save to DB
            $match = AiJobMatch::firstOrCreate(
                ['job_application_id' => $job->id],
                ['match_score' => 0, 'verdict' => 'Pending']
            );
            
            $match->update(['interview_prep_questions' => $questions]);

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
            Do not include any markdown formatting (like ```json), just output raw JSON text.";

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2, 
            ]);

            $rawOutput = $response->choices[0]->message->content;
            $rawOutput = str_replace(['```json', '```'], '', $rawOutput);
            
            $result = json_decode(trim($rawOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                throw new Exception("AI returned invalid JSON structure.");
            }

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

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1, 
            ]);

            $rawOutput = $response->choices[0]->message->content;
            $rawOutput = str_replace(['```json', '```'], '', $rawOutput);
            
            $result = json_decode(trim($rawOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                throw new Exception("AI returned invalid JSON structure.");
            }

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

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5, 
            ]);

            $rawOutput = str_replace(['```json', '```'], '', $response->choices[0]->message->content);
            $result = json_decode(trim($rawOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                throw new Exception("AI returned invalid JSON.");
            }

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

            $response = OpenAI::chat()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5, 
            ]);

            $rawOutput = str_replace(['```json', '```'], '', $response->choices[0]->message->content);
            $result = json_decode(trim($rawOutput), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                throw new Exception("AI returned invalid JSON.");
            }

            return response()->json([
                'success' => true,
                'negotiation' => $result
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
