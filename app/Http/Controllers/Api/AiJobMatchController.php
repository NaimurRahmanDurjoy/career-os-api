<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\AiJobMatch;
use App\Jobs\ProcessJobMatchJob;
use Illuminate\Http\Request;

class AiJobMatchController extends Controller
{
    // POST /api/jobs/{id}/ai-match
    public function analyze(Request $request, $id)
    {
        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($id);
        
        if (empty($job->job_description)) {
            return response()->json(['message' => 'Job description is required for AI matching.'], 400);
        }

        $resume = $job->resume ?? $request->user()->resumes()->where('status', 'completed')->latest()->first();

        if (!$resume) {
            return response()->json(['message' => 'No valid resume found to match against.'], 400);
        }

        // Only use safe structured data or raw text to save tokens
        $resumeText = json_encode($resume->parsed_content['structured_data'] ?? []);

        // Find or create match record
        $match = AiJobMatch::firstOrCreate(
            ['job_application_id' => $job->id],
            ['match_score' => 0, 'verdict' => 'Processing...']
        );
        
        // Reset to processing if user requests re-analysis
        $match->update(['verdict' => 'Processing...']);

        // Dispatch background job
        ProcessJobMatchJob::dispatch($match, $resumeText, $job->job_description);

        return response()->json([
            'message' => 'AI Job Match analysis started.',
            'match' => $match
        ], 202);
    }

    // GET /api/jobs/{id}/ai-match
    public function show(Request $request, $id)
    {
        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($id);
        $match = $job->aiJobMatch ?? AiJobMatch::where('job_application_id', $job->id)->first();

        if (!$match) {
            return response()->json(['message' => 'No match analysis found for this job.'], 404);
        }

        return response()->json($match);
    }
}
