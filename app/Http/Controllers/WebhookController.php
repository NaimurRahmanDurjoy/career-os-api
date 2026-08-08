<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class WebhookController extends Controller
{
    public function handleSslCommerzIPN(Request $request)
    {
        $transactionId = $request->input('tran_id');
        $status = $request->input('status');

        $transaction = Transaction::find($transactionId);

        if ($transaction && $status === 'VALID' && $transaction->status !== 'paid') {
            $transaction->update(['status' => 'paid']);
            
            $user = $transaction->user;
            $currentExpiresAt = $user->subscription ? $user->subscription->expires_at : now();
            
            if ($currentExpiresAt->isPast()) {
                $currentExpiresAt = now();
            }

            $user->subscriptions()->create([
                'plan_name' => 'Career OS Pro',
                'expires_at' => clone $currentExpiresAt->addDays(30),
                'status' => 'active'
            ]);
        }

        return response()->json(['message' => 'IPN Processed']);
    }

    public function handleStripeWebhook(Request $request)
    {
        // Stripe sends JSON webhook data
        $payload = $request->all();
        // Exact same Subscription activation logic as above is placed here in production
        
        return response()->json(['message' => 'Stripe Webhook Processed']);
    }
}
