<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\ApplicationNote;
use Illuminate\Http\Request;

class ApplicationNoteController extends Controller
{
    // GET /api/jobs/{jobId}/notes
    public function index(Request $request, $jobId)
    {
        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($jobId);
        $notes = $job->notes()->orderBy('created_at', 'desc')->get();
        return response()->json($notes);
    }

    // POST /api/jobs/{jobId}/notes
    public function store(Request $request, $jobId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $job = JobApplication::where('user_id', $request->user()->id)->findOrFail($jobId);

        $note = $job->notes()->create([
            'title' => $request->title,
            'content' => $request->content,
        ]);

        return response()->json($note, 201);
    }

    // PUT /api/notes/{id}
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $note = ApplicationNote::whereHas('jobApplication', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);

        $note->update($request->only(['title', 'content']));

        return response()->json($note);
    }

    // DELETE /api/notes/{id}
    public function destroy(Request $request, $id)
    {
        $note = ApplicationNote::whereHas('jobApplication', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);

        $note->delete();

        return response()->json(['message' => 'Note deleted successfully']);
    }
}
