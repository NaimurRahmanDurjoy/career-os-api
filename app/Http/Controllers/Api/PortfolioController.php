<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PortfolioController extends Controller
{
    /**
     * Get public portfolio details by username.
     * Accessible without authentication.
     */
    public function show($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Portfolio not found.'
            ], 404);
        }

        if (!$user->is_portfolio_public) {
            return response()->json([
                'success' => false,
                'message' => 'This portfolio is private.'
            ], 403);
        }

        // Get primary resume or latest active resume
        $resume = Resume::where('user_id', $user->id)
            ->where('status', '!=', 'failed')
            ->orderByDesc('is_primary')
            ->latest()
            ->first();

        // Extract structured CV fields if parsed_content exists
        $cvData = $resume ? $resume->parsed_content : null;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => $user->name,
                    'username' => $user->username,
                    'headline' => $user->headline ?? ($cvData['headline'] ?? $cvData['target_role'] ?? 'Professional'),
                    'bio' => $user->bio ?? ($cvData['summary'] ?? $cvData['professional_summary'] ?? ''),
                    'social_links' => $user->social_links ?? [],
                    'featured_projects' => $user->featured_projects ?? ($cvData['projects'] ?? []),
                    'is_portfolio_public' => $user->is_portfolio_public,
                ],
                'resume' => [
                    'skills' => $cvData['skills'] ?? [],
                    'experience' => $cvData['experience'] ?? $cvData['work_experience'] ?? [],
                    'education' => $cvData['education'] ?? [],
                    'certifications' => $cvData['certifications'] ?? [],
                    'languages' => $cvData['languages'] ?? [],
                    'pdf_available' => (bool)$resume?->file_path,
                    'resume_id' => $resume?->id,
                ]
            ]
        ]);
    }

    /**
     * Update current authenticated user's portfolio settings.
     */
    public function updatePortfolio(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:30',
                Rule::unique('users')->ignore($user->id)
            ],
            'headline' => 'nullable|string|max:150',
            'bio' => 'nullable|string|max:1000',
            'is_portfolio_public' => 'boolean',
            'social_links' => 'nullable|array',
            'social_links.github' => 'nullable|string|max:255',
            'social_links.linkedin' => 'nullable|string|max:255',
            'social_links.twitter' => 'nullable|string|max:255',
            'social_links.website' => 'nullable|string|max:255',
            'featured_projects' => 'nullable|array',
            'featured_projects.*.title' => 'required|string|max:100',
            'featured_projects.*.description' => 'nullable|string|max:500',
            'featured_projects.*.link' => 'nullable|string|max:255',
            'featured_projects.*.tech_stack' => 'nullable|array',
        ]);

        $user->update([
            'username' => strtolower($validated['username']),
            'headline' => $validated['headline'] ?? $user->headline,
            'bio' => $validated['bio'] ?? $user->bio,
            'is_portfolio_public' => $validated['is_portfolio_public'] ?? true,
            'social_links' => $validated['social_links'] ?? $user->social_links,
            'featured_projects' => $validated['featured_projects'] ?? $user->featured_projects,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Portfolio settings updated successfully!',
            'data' => [
                'username' => $user->username,
                'headline' => $user->headline,
                'bio' => $user->bio,
                'is_portfolio_public' => $user->is_portfolio_public,
                'social_links' => $user->social_links,
                'featured_projects' => $user->featured_projects,
            ]
        ]);
    }
}
