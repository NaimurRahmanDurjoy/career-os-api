<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // GET /api/dashboard/stats
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        
        // Aggregate counts by status
        $statusCounts = JobApplication::where('user_id', $userId)
            ->selectRaw("status, count(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');
        
        $driver = \Illuminate\Support\Facades\DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        $weekSelect = match($driver) {
            'sqlite' => "strftime('%Y-%W', applied_at) as week",
            'pgsql'  => "to_char(applied_at, 'IYYY-IW') as week",
            'mysql'  => "DATE_FORMAT(applied_at, '%Y-%u') as week",
            default  => "to_char(applied_at, 'YYYY-WW') as week",
        };

        // Weekly trend data (last 8 weeks)
        $weeklyData = JobApplication::where('user_id', $userId)
            ->where('applied_at', '>=', now()->subWeeks(8))
            ->selectRaw("$weekSelect, count(*) as count") 
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        $countsArray = $statusCounts->toArray();
        $total = array_sum($countsArray);
        
        $interviewCount = $countsArray['interview'] ?? 0;
        $offerCount = $countsArray['offer'] ?? 0;

        return response()->json([
            'total'          => $total,
            'applied'        => $countsArray['applied'] ?? 0,
            'shortlisted'    => $countsArray['shortlisted'] ?? 0,
            'interview'      => $interviewCount,
            'offer'          => $offerCount,
            'rejected'       => $countsArray['rejected'] ?? 0,
            'interview_rate' => $total > 0 ? round(($interviewCount / $total) * 100, 1) : 0,
            'success_rate'   => $total > 0 ? round(($offerCount / $total) * 100, 1) : 0,
            'weekly_data'    => $weeklyData,
        ]);
    }
}
