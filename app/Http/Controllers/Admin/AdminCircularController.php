<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobCircular;
use Illuminate\Http\Request;

class AdminCircularController extends Controller
{
    public function index(Request $request)
    {
        $query = JobCircular::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('organization', 'ilike', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        $circulars = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($circulars);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'organization' => 'required|string|max:255',
            'category' => 'required|string|in:govt,bank,private,remote',
            'location' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:50',
            'experience_level' => 'nullable|string|max:100',
            'salary_range' => 'nullable|string|max:100',
            'application_deadline' => 'nullable|date',
            'source_url' => 'nullable|url',
            'circular_pdf_url' => 'nullable|url',
            'description' => 'required|string',
            'requirements' => 'nullable|string',
            'skills_tags' => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['source_type'] = 'admin';
        $validated['location'] = $validated['location'] ?? 'Bangladesh';
        $validated['job_type'] = $validated['job_type'] ?? 'full-time';
        $validated['skills_tags'] = $validated['skills_tags'] ?? [];
        $validated['is_featured'] = $request->boolean('is_featured', false);
        $validated['is_active'] = $request->boolean('is_active', true);

        $circular = JobCircular::create($validated);

        return response()->json([
            'message' => 'Circular published successfully',
            'circular' => $circular
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $circular = JobCircular::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'organization' => 'required|string|max:255',
            'category' => 'required|string|in:govt,bank,private,remote',
            'location' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:50',
            'experience_level' => 'nullable|string|max:100',
            'salary_range' => 'nullable|string|max:100',
            'application_deadline' => 'nullable|date',
            'source_url' => 'nullable|url',
            'circular_pdf_url' => 'nullable|url',
            'description' => 'required|string',
            'requirements' => 'nullable|string',
            'skills_tags' => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $circular->update($validated);

        return response()->json([
            'message' => 'Circular updated successfully',
            'circular' => $circular
        ]);
    }

    public function destroy($id)
    {
        $circular = JobCircular::findOrFail($id);
        $circular->delete();

        return response()->json([
            'message' => 'Circular deleted successfully'
        ]);
    }
}
