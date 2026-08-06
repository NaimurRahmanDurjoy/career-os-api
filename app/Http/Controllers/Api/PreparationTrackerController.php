<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PreparationTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreparationTrackerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $trackers = PreparationTracker::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($trackers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_type' => 'required|string|max:255',
            'syllabus_roadmap' => 'nullable|array',
        ]);

        $roadmap = $validated['syllabus_roadmap'] ?? $this->getDefaultRoadmap($validated['exam_type']);

        $tracker = PreparationTracker::create([
            'user_id' => Auth::id(),
            'exam_type' => $validated['exam_type'],
            'syllabus_roadmap' => $roadmap,
            'overall_progress' => 0,
        ]);

        return response()->json($tracker, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $tracker = PreparationTracker::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($tracker);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'exam_type' => 'sometimes|string|max:255',
            'syllabus_roadmap' => 'sometimes|array',
            'overall_progress' => 'sometimes|integer|min:0|max:100',
        ]);

        $tracker = PreparationTracker::where('user_id', Auth::id())->findOrFail($id);
        $tracker->update($validated);

        return response()->json($tracker);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tracker = PreparationTracker::where('user_id', Auth::id())->findOrFail($id);
        $tracker->delete();
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Get a default roadmap based on exam type.
     */
    private function getDefaultRoadmap(string $examType): array
    {
        // Simple default mapping
        $exams = [
            'BCS' => [
                ['moduleName' => 'Bangla Language & Literature', 'progress' => 0, 'topics' => [['name' => 'Grammar', 'completed' => false], ['name' => 'Literature history', 'completed' => false]]],
                ['moduleName' => 'English Language & Literature', 'progress' => 0, 'topics' => [['name' => 'Grammar', 'completed' => false], ['name' => 'Vocabulary', 'completed' => false]]],
                ['moduleName' => 'Bangladesh Affairs', 'progress' => 0, 'topics' => [['name' => 'History', 'completed' => false], ['name' => 'Geography', 'completed' => false]]],
                ['moduleName' => 'International Affairs', 'progress' => 0, 'topics' => [['name' => 'Global events', 'completed' => false], ['name' => 'Organizations', 'completed' => false]]],
                ['moduleName' => 'Mental Ability & Math', 'progress' => 0, 'topics' => [['name' => 'Algebra', 'completed' => false], ['name' => 'Geometry', 'completed' => false]]],
            ],
            'Bank Job' => [
                ['moduleName' => 'Mathematics', 'progress' => 0, 'topics' => [['name' => 'Arithmetic', 'completed' => false], ['name' => 'Algebra', 'completed' => false]]],
                ['moduleName' => 'English', 'progress' => 0, 'topics' => [['name' => 'Reading Comprehension', 'completed' => false], ['name' => 'Grammar', 'completed' => false]]],
                ['moduleName' => 'General Knowledge', 'progress' => 0, 'topics' => [['name' => 'Current Affairs', 'completed' => false], ['name' => 'Banking terms', 'completed' => false]]],
                ['moduleName' => 'Computer Knowledge', 'progress' => 0, 'topics' => [['name' => 'Basic IT', 'completed' => false], ['name' => 'MS Office', 'completed' => false]]],
            ],
        ];
        
        // Return matching or a generic one
        return $exams[$examType] ?? [
            ['moduleName' => 'General Syllabus', 'progress' => 0, 'topics' => [['name' => 'Topic 1', 'completed' => false], ['name' => 'Topic 2', 'completed' => false]]]
        ];
    }
}
