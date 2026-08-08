<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class BillingController extends Controller
{
    public function getPlans()
    {
        return response()->json([
            'plans' => [
                [
                    'id' => 'pro_monthly',
                    'name' => 'Career OS Pro',
                    'price_bdt' => 1000,
                    'price_usd' => 10,
                    'features' => ['Unlimited AI Mock Tests', 'Advanced Resume Parsing', 'Priority Support']
                ]
            ]
        ]);
    }

    public function initiateCheckout(Request $request)
    {
        $request->validate([
            'gateway' => 'required|in:stripe,sslcommerz',
            'plan_id' => 'required'
        ]);

        $gateway = $request->gateway;
        $amount = $gateway === 'sslcommerz' ? 1000 : 10;
        $currency = $gateway === 'sslcommerz' ? 'BDT' : 'USD';

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending'
        ]);

        // Generating mock redirect URLs depending on gateway
        // In production, integrate Official SDKs to get actual Hosted URLs
        $checkoutUrl = $gateway === 'sslcommerz' 
            ? 'https://sandbox.sslcommerz.com/mock-checkout/' . $transaction->id
            : 'https://checkout.stripe.com/pay/mock_' . $transaction->id;

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'transaction_id' => $transaction->id
        ]);
    }
}
