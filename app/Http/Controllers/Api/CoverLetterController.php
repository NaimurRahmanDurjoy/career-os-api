<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\CoverLetter;
use OpenAI\Laravel\Facades\OpenAI;
use Exception;

class CoverLetterController extends Controller
{
    public function show(Request $request, $jobId)
    {
        $coverLetter = CoverLetter::where('user_id', $request->user()->id)
            ->where('job_id', $jobId)
            ->first();

        return response()->json([
            'success' => true,
            'cover_letter' => $coverLetter
        ]);
    }

    public function generate(Request $request, $jobId)
    {
        if ($request->user()->usage['ai_tools'] >= $request->user()->limits['ai_tools']) {
            return response()->json(['success' => false, 'message' => 'Monthly allowance reached for AI Tools.'], 403);
        }

        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($jobId);
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
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.5,
            ]);

            $content = trim($response->choices[0]->message->content);

            $coverLetter = CoverLetter::updateOrCreate(
                ['user_id' => $request->user()->id, 'job_id' => $job->id],
                ['content' => $content]
            );

            $request->user()->aiUsageLogs()->create(['feature_name' => 'cover_letter']);

            return response()->json([
                'success' => true,
                'cover_letter' => $coverLetter
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request, $jobId)
    {
        $request->validate(['content' => 'required|string']);
        
        $coverLetter = CoverLetter::where('user_id', $request->user()->id)
            ->where('job_id', $jobId)
            ->firstOrFail();
            
        $coverLetter->update(['content' => $request->content]);
        
        return response()->json(['success' => true, 'cover_letter' => $coverLetter]);
    }
}
