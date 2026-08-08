<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Plan;

class BillingController extends Controller
{
    public function getPlans()
    {
        $plans = Plan::where('is_active', true)->get()->map(function ($plan) {
            return [
                'id' => $plan->identifier,
                'name' => $plan->name,
                'price_bdt' => $plan->price_bdt,
                'price_usd' => $plan->price_usd,
                'features' => $plan->features ?? [],
                'popular' => $plan->is_popular,
            ];
        });

        return response()->json([
            'plans' => $plans
        ]);
    }

    public function initiateCheckout(Request $request)
    {
        $request->validate([
            'gateway' => 'required|in:stripe,sslcommerz',
            'plan_id' => 'required'
        ]);

        $gateway = $request->gateway;
        $planId = $request->plan_id;
        
        $selectedPlan = Plan::where('identifier', $planId)->first();

        if (!$selectedPlan || !$selectedPlan->is_active) {
            return response()->json(['message' => 'Invalid or inactive plan specified'], 400);
        }

        $amount = $gateway === 'sslcommerz' ? $selectedPlan->price_bdt : $selectedPlan->price_usd;
        $currency = $gateway === 'sslcommerz' ? 'BDT' : 'USD';

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'gateway' => $gateway,
            'plan_id' => $selectedPlan->identifier,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending'
        ]);

        $checkoutUrl = $gateway === 'sslcommerz' 
            ? 'https://sandbox.sslcommerz.com/mock-checkout/' . $transaction->id
            : 'https://checkout.stripe.com/pay/mock_' . $transaction->id;

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'transaction_id' => $transaction->id
        ]);
    }
}
