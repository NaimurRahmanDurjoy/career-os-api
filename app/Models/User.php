<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    protected $appends = ['current_plan', 'limits', 'usage'];

    public function getCurrentPlanAttribute()
    {
        $sub = $this->subscription;
        if ($sub && $sub->expires_at && $sub->expires_at->isFuture()) {
            $plan = \App\Models\Plan::where('name', $sub->plan_name)->first();
            return [
                'name' => $sub->plan_name,
                'identifier' => $plan ? $plan->identifier : 'pro_monthly',
                'days_remaining' => (int) round(now()->diffInDays($sub->expires_at, false))
            ];
        }
        return [
            'name' => 'Career OS Basic (Free)',
            'identifier' => 'basic',
            'days_remaining' => null
        ];
    }

    public function getLimitsAttribute()
    {
        $defaultLimits = ['mock_tests' => 2, 'resumes' => 2, 'ai_tools' => 2, 'job_match' => false, 'jobs' => 10];

        try {
            $planId = $this->current_plan['identifier'];
            $plan = \App\Models\Plan::where('identifier', $planId)->first();
            
            if ($plan && $plan->limits) {
                return array_merge($defaultLimits, $plan->limits);
            }
        } catch (\Exception $e) {
            // DB table/column might not exist yet during migration
        }

        return $defaultLimits;
    }

    public function currentCycleStart()
    {
        $sub = $this->subscription;
        if ($sub && $sub->expires_at && $sub->expires_at->isFuture()) {
            return $sub->created_at;
        }
        return now()->startOfMonth();
    }

    public function getUsageAttribute()
    {
        try {
            $cycleStart = $this->currentCycleStart();
            return [
                'jobs' => $this->jobApplications()->where('created_at', '>=', $cycleStart)->count(),
                'mock_tests' => \App\Models\AiMockTest::where('user_id', $this->id)->where('created_at', '>=', $cycleStart)->count(),
                'resumes' => $this->resumes()->where('status', '!=', 'failed')->where('created_at', '>=', $cycleStart)->count(),
                'ai_tools' => $this->aiUsageLogs()->where('created_at', '>=', $cycleStart)->count(),
            ];
        } catch (\Exception $e) {
            // Graceful fallback to prevent 500 errors if the DB migration isn't run yet
            return [
                'jobs' => 0,
                'mock_tests' => 0,
                'resumes' => 0,
                'ai_tools' => 0,
            ];
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'settings',
        'provider',
        'provider_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public function resumes()
    {
        return $this->hasMany(Resume::class);
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function aiUsageLogs()
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function hasActiveSubscription()
    {
        $sub = $this->subscription;
        return $sub && $sub->expires_at && $sub->expires_at->isFuture();
    }
}
