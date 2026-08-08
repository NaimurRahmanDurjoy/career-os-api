<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Resume;
use App\Models\AiMockTest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $metrics = [
            'total_users' => User::count(),
            'total_resumes' => Resume::count(),
            'total_mock_tests' => AiMockTest::count(),
        ];

        return response()->json($metrics);
    }
}
