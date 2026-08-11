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
        // 30 day usage aggregates
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        
        $dailyTransactions = Transaction::where('status', 'paid')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $dailyMockTests = AiMockTest::where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_tests')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Build continuous 30-day map
        $chartData = [];
        $currentDate = clone $thirtyDaysAgo;
        while ($currentDate <= now()) {
            $dateString = $currentDate->format('Y-m-d');
            $chartData[$dateString] = [
                'date' => $currentDate->format('M d'),
                'revenue' => 0,
                'ai_tests' => 0,
            ];
            $currentDate->addDay();
        }

        foreach ($dailyTransactions as $tx) {
            $dateString = clone \Carbon\Carbon::parse($tx->date);
            $key = $dateString->format('Y-m-d');
            if (isset($chartData[$key])) {
                $chartData[$key]['revenue'] = (float)$tx->total_amount;
            }
        }

        foreach ($dailyMockTests as $mt) {
            $dateString = clone \Carbon\Carbon::parse($mt->date);
            $key = $dateString->format('Y-m-d');
            if (isset($chartData[$key])) {
                $chartData[$key]['ai_tests'] = (int)$mt->total_tests;
            }
        }

        $metrics = [
            'total_users' => User::count(),
            'total_resumes' => Resume::count(),
            'total_mock_tests' => AiMockTest::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->where('expires_at', '>', now())->count(),
            'revenue_bdt' => Transaction::where('status', 'paid')->where('currency', 'BDT')->sum('amount'),
            'revenue_usd' => Transaction::where('status', 'paid')->where('currency', 'USD')->sum('amount'),
            'recent_transactions' => Transaction::with('user:id,name,email')->where('status', 'paid')->latest()->take(5)->get(),
            'chart_data' => array_values($chartData),
        ];

        return response()->json($metrics);
    }

    public function users(Request $request)
    {
        $users = User::orderBy('created_at', 'desc')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'provider' => $user->provider,
                'is_active' => $user->is_active,
                'current_plan' => $user->current_plan, // Automatically uses appends accessor
                'created_at' => $user->created_at,
            ];
        });

        return response()->json($users);
    }

    public function toggleUserStatus(Request $request, $id)
    {
        $user = clone User::findOrFail($id);
        $user->is_active = !$user->is_active;

        if (!$user->is_active) {
            $settings = $user->settings ?? [];
            $settings['suspension_reason'] = $request->input('suspension_reason', null);
            $user->settings = $settings;
        } else {
            $settings = $user->settings ?? [];
            unset($settings['suspension_reason']);
            $user->settings = $settings;
        }

        $user->save();

        return response()->json([
            'message' => 'User status updated',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'provider' => $user->provider,
                'is_active' => $user->is_active,
                'current_plan' => $user->current_plan,
                'created_at' => $user->created_at,
            ]
        ]);
    }
}
