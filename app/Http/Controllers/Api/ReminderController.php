<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reminder;

class ReminderController extends Controller
{
    /**
     * Get all upcoming reminders for the user
     */
    public function index(Request $request)
    {
        $reminders = Reminder::where('user_id', $request->user()->id)
            ->with('jobApplication')
            ->orderBy('remind_at', 'asc')
            ->get();
            
        return response()->json(['reminders' => $reminders]);
    }

    /**
     * Get 5 upcoming incomplete reminders for dashboard bell
     */
    public function upcoming(Request $request)
    {
        $reminders = Reminder::where('user_id', $request->user()->id)
            ->where('is_completed', false)
            ->where('remind_at', '>=', now())
            ->orderBy('remind_at', 'asc')
            ->limit(5)
            ->get();
            
        return response()->json(['reminders' => $reminders]);
    }

    /**
     * Create a new reminder
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_application_id' => 'nullable|uuid|exists:job_applications,id',
            'type' => 'required|string|in:interview,follow_up,deadline,custom',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'remind_at' => 'required|date'
        ]);

        $validated['user_id'] = $request->user()->id;

        $reminder = Reminder::create($validated);

        return response()->json([
            'message' => 'Reminder created successfully',
            'reminder' => $reminder
        ], 201);
    }

    /**
     * Update a reminder
     */
    public function update(Request $request, $id)
    {
        $reminder = Reminder::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'is_completed' => 'sometimes|boolean',
            'type' => 'sometimes|string|in:interview,follow_up,deadline,custom',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'remind_at' => 'sometimes|date'
        ]);

        $reminder->update($validated);

        return response()->json([
            'message' => 'Reminder updated successfully',
            'reminder' => $reminder
        ]);
    }

    /**
     * Delete a reminder
     */
    public function destroy(Request $request, $id)
    {
        $reminder = Reminder::where('user_id', $request->user()->id)->findOrFail($id);
        $reminder->delete();

        return response()->json([
            'message' => 'Reminder deleted successfully'
        ]);
    }
}
