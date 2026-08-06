<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\AiJobMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobApplicationController extends Controller
{
    public function index()
    {
        $jobs = JobApplication::where('user_id', Auth::id())->with('aiMatch')->orderBy('applied_at', 'desc')->get();
        return response()->json($jobs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'salary_range' => 'nullable|string|max:255',
            'status' => 'required|in:applied,shortlisted,interview,offer,rejected',
            'job_url' => 'nullable|url',
            'applied_at' => 'required|date',
            'resume_id' => 'nullable|exists:resumes,id'
        ]);

        $validated['user_id'] = Auth::id();

        $jobApplication = JobApplication::create($validated);

        return response()->json($jobApplication, 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:applied,shortlisted,interview,offer,rejected',
        ]);

        $jobApplication = JobApplication::where('user_id', Auth::id())->findOrFail($id);
        $jobApplication->update(['status' => $validated['status']]);

        return response()->json($jobApplication);
    }

    public function destroy($id)
    {
        $jobApplication = JobApplication::where('user_id', Auth::id())->findOrFail($id);
        $jobApplication->delete();

        return response()->json(['message' => 'Job application deleted successfully']);
    }

    public function saveCoverLetter(Request $request, $id)
    {
        $validated = $request->validate([
            'cover_letter' => 'required|string',
        ]);

        $jobApplication = JobApplication::where('user_id', Auth::id())->findOrFail($id);

        $match = AiJobMatch::firstOrCreate(
            ['job_application_id' => $jobApplication->id],
            ['match_score' => 0, 'verdict' => 'Pending']
        );
        $match->update(['generated_cover_letter' => $validated['cover_letter']]);

        return response()->json(['success' => true]);
    }
}
