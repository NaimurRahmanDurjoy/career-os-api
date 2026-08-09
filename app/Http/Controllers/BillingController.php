<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Plan;
use Illuminate\Support\Facades\Http;

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

        if ($gateway === 'sslcommerz') {
            $apiUrl = env('SSLCOMMERZ_TESTMODE', true) 
                ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' 
                : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
                
            $response = Http::asForm()->post($apiUrl, [
                'store_id' => env('SSLCOMMERZ_STORE_ID'),
                'store_passwd' => env('SSLCOMMERZ_STORE_PASSWORD'),
                'total_amount' => $amount,
                'currency' => $currency,
                'tran_id' => $transaction->id,
                'success_url' => url('/api/webhooks/sslcommerz/success'),
                'fail_url' => url('/api/webhooks/sslcommerz/fail'),
                'cancel_url' => url('/api/webhooks/sslcommerz/cancel'),
                'cus_name' => $request->user()->name,
                'cus_email' => $request->user()->email,
                'cus_phone' => '01700000000',
                'shipping_method' => 'NO',
                'product_name' => $selectedPlan->name . ' Subscription',
                'product_category' => 'Software',
                'product_profile' => 'non-physical-goods',
            ]);

            $result = $response->json();
            
            if (isset($result['status']) && $result['status'] === 'SUCCESS') {
                $checkoutUrl = $result['GatewayPageURL'];
            } else {
                return response()->json(['message' => 'SSLCommerz failed: ' . ($result['failedreason'] ?? 'Unknown Error')], 500);
            }
        } else {
            // Stripe
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $selectedPlan->name . ' Plan',
                        ],
                        'unit_amount' => intval($amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/api/webhooks/stripe/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/api/webhooks/stripe/cancel'),
                'client_reference_id' => $transaction->id,
                'metadata' => [
                    'transaction_id' => $transaction->id,
                ]
            ]);
            
            $checkoutUrl = $session->url;
        }

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'transaction_id' => $transaction->id
        ]);
    }
}
