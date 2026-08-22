<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobContact;
use App\Models\JobApplication;

class JobContactController extends Controller
{
    public function index(Request $request, $jobId)
    {
        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($jobId);
        $contacts = JobContact::where('job_id', $job->id)->orderBy('created_at', 'desc')->get();
        return response()->json($contacts);
    }

    public function store(Request $request, $jobId)
    {
        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($jobId);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'last_contact_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);
        
        $validated['job_id'] = $job->id;
        $contact = JobContact::create($validated);
        
        return response()->json($contact, 201);
    }
    
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'last_contact_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);
        
        $contact = JobContact::findOrFail($id);
        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($contact->job_id);
        
        $contact->update($validated);
        return response()->json($contact);
    }

    public function destroy(Request $request, $id)
    {
        $contact = JobContact::findOrFail($id);
        JobApplication::where('user_id', $request->user()->id)->findOrFail($contact->job_id);
        $contact->delete();
        
        return response()->json(['message' => 'Deleted']);
    }
}
