<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Resume;
use App\Models\AiMockTest;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $metrics = [
            'total_users' => User::count(),
            'total_resumes' => Resume::count(),
            'total_mock_tests' => AiMockTest::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->where('expires_at', '>', now())->count(),
            'revenue_bdt' => Transaction::where('status', 'paid')->where('currency', 'BDT')->sum('amount'),
            'revenue_usd' => Transaction::where('status', 'paid')->where('currency', 'USD')->sum('amount'),
            'recent_transactions' => Transaction::with('user:id,name,email')->where('status', 'paid')->latest()->take(5)->get(),
        ];

        return response()->json($metrics);
    }
}
