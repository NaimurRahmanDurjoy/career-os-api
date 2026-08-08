<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::create([
            'identifier' => 'basic',
            'name' => 'Career OS Basic (Free)',
            'price_bdt' => 0,
            'price_usd' => 0,
            'features' => ['1 AI Mock Test', 'Basic Profile', 'Community Support'],
            'limits' => ['mock_tests' => 1, 'resumes' => 1, 'ai_tools' => 1, 'job_match' => false],
            'is_popular' => false,
            'is_active' => true,
        ]);

        Plan::create([
            'identifier' => 'starter',
            'name' => 'Career OS Starter',
            'price_bdt' => 300,
            'price_usd' => 3,
            'features' => ['5 AI Mock Tests', 'Basic Resume Parsing', 'Standard Support'],
            'limits' => ['mock_tests' => 5, 'resumes' => 3, 'ai_tools' => 10, 'job_match' => true],
            'is_popular' => false,
            'is_active' => true,
        ]);

        Plan::create([
            'identifier' => 'pro_monthly',
            'name' => 'Career OS Pro',
            'price_bdt' => 800,
            'price_usd' => 8,
            'features' => ['Unlimited AI Mock Tests', 'Advanced Resume Parsing', 'Priority Support'],
            'limits' => ['mock_tests' => 999999, 'resumes' => 999999, 'ai_tools' => 999999, 'job_match' => true],
            'is_popular' => true,
            'is_active' => true,
        ]);

        Plan::create([
            'identifier' => 'ultra',
            'name' => 'Career OS Ultra',
            'price_bdt' => 1000,
            'price_usd' => 10,
            'features' => ['Everything in Pro', '1-on-1 Interview Coaching', 'Dedicated Account Manager'],
            'limits' => ['mock_tests' => 999999, 'resumes' => 999999, 'ai_tools' => 999999, 'job_match' => true],
            'is_popular' => false,
            'is_active' => true,
        ]);
    }
}
