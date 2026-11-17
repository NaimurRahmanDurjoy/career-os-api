<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobCircular;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Carbon\Carbon;

class JobCircularController extends Controller
{
    /**
     * Display a listing of circulars with search, categories, and status filters.
     */
    public function index(Request $request)
    {
        $query = JobCircular::query();

        // 60-day safety filter: never show anything older than 60 days post-deadline
        $sixtyDaysAgo = Carbon::now()->subDays(60);
        $query->where(function ($q) use ($sixtyDaysAgo) {
            $q->whereNull('application_deadline')
              ->orWhere('application_deadline', '>=', $sixtyDaysAgo);
        });

        // Search by title, organization, or requirements
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('organization', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('requirements', 'ilike', "%{$search}%");
            });
        }

        // Category Filter ('govt', 'private', 'remote', 'bank')
        if ($category = $request->query('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        // Status Filter: 'active' (default), 'expired', or 'all'
        $status = $request->query('status', 'all');
        $now = Carbon::now();
        if ($status === 'active') {
            $query->where(function ($q) use ($now) {
                $q->whereNull('application_deadline')
                  ->orWhere('application_deadline', '>=', $now);
            });
        } elseif ($status === 'expired') {
            $query->whereNotNull('application_deadline')
                  ->where('application_deadline', '<', $now);
        }

        // Location / Job Type filter
        if ($jobType = $request->query('job_type')) {
            $query->where('job_type', $jobType);
        }

        // Ordering: Featured first, then closest upcoming deadline, then newest
        $query->orderBy('is_featured', 'desc')
              ->orderByRaw('CASE WHEN application_deadline >= NOW() THEN 0 ELSE 1 END')
              ->orderBy('application_deadline', 'asc')
              ->orderBy('created_at', 'desc');

        $circulars = $query->paginate($request->query('per_page', 12));

        return response()->json($circulars);
    }

    /**
     * Display single circular details and increment view count.
     */
    public function show($id)
    {
        $circular = JobCircular::findOrFail($id);
        $circular->increment('views_count');

        return response()->json([
            'circular' => $circular
        ]);
    }

    /**
     * 1-Click Track: Import a circular directly into the authenticated user's Kanban board.
     */
    public function trackInKanban(Request $request, $id)
    {
        $user = $request->user();
        $circular = JobCircular::findOrFail($id);

        // Check if already tracked by checking matching company & role
        $existing = JobApplication::where('user_id', $user->id)
            ->where('company_name', $circular->organization)
            ->where('role', $circular->title)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Job already exists in your tracker.',
                'application' => $existing,
                'already_tracked' => true
            ], 200);
        }

        $application = JobApplication::create([
            'user_id' => $user->id,
            'role' => $circular->title,
            'company_name' => $circular->organization,
            'job_url' => $circular->source_url,
            'salary_range' => $circular->salary_range,
            'job_description' => $circular->description,
            'status' => 'applied',
            'applied_at' => Carbon::now()
        ]);

        return response()->json([
            'message' => 'Job successfully added to your tracker (Applied column).',
            'application' => $application,
            'already_tracked' => false
        ], 201);
    }
}
