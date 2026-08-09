<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class WebhookController extends Controller
{
    protected function activateSslCommerzTransaction(Request $request)
    {
        $valId = $request->input('val_id');
        $transactionId = $request->input('tran_id');
        $status = $request->input('status');
        $amount = $request->input('amount');

        if (!$valId || !$transactionId) return false;

        $storeId = env('SSLCOMMERZ_STORE_ID', 'testbox');
        $storePassword = env('SSLCOMMERZ_STORE_PASSWORD', 'testbox');
        
        $apiUrl = "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php?val_id={$valId}&store_id={$storeId}&store_passwd={$storePassword}&v=1&format=json";
        
        try {
            $response = Http::get($apiUrl);
            $validation = $response->json();
            
            if (empty($validation) || !isset($validation['status']) || ($validation['status'] !== 'VALID' && $validation['status'] !== 'LIMIT_PASSED')) {
                Log::warning('SSLCommerz verification failed: ' . json_encode($validation));
                Log::warning('SSLCommerz RAW RESPONSE: ' . $response->body());
                return false;
            }
            
            if (floatval($validation['amount']) !== floatval($amount)) {
                Log::warning('SSLCommerz amount mismatch.');
                return false;
            }
        } catch (\Exception $e) {
            Log::error('SSLCommerz IPN verification request exception: ' . $e->getMessage());
            if (app()->environment('production')) return false;
        }

        $transaction = Transaction::find($transactionId);

        if ($transaction && ($status === 'VALID' || $status === 'SUCCESS') && $transaction->status !== 'paid') {
            $transaction->update(['status' => 'paid']);
            
            $user = $transaction->user;
            $currentExpiresAt = $user->subscription ? $user->subscription->expires_at : now();
            
            if ($currentExpiresAt->isPast()) $currentExpiresAt = now();

            $planName = 'Career OS Pro';
            if ($transaction->plan_id) {
                $plan = \App\Models\Plan::where('identifier', $transaction->plan_id)->first();
                if ($plan) $planName = $plan->name;
            }

            $user->subscriptions()->create([
                'plan_name' => $planName,
                'expires_at' => clone $currentExpiresAt->addDays(30),
                'status' => 'active'
            ]);
            return true;
        }
        return false;
    }

    public function handleSslCommerzIPN(Request $request)
    {
        $this->activateSslCommerzTransaction($request);
        return response()->json(['message' => 'IPN Processed']);
    }

    public function handleStripeWebhook(Request $request)
    {
        $signatureHeader = $request->header('Stripe-Signature');
        $payload = $request->getContent();
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        if ($webhookSecret && $signatureHeader) {
            // Parse Stripe-Signature: t=1612345678,v1=sha256_hash_here,v0=another_hash
            $parts = explode(',', $signatureHeader);
            $timestamp = null;
            $signatures = [];

            foreach ($parts as $part) {
                $subparts = explode('=', trim($part), 2);
                if (count($subparts) === 2) {
                    if ($subparts[0] === 't') {
                        $timestamp = $subparts[1];
                    } elseif ($subparts[0] === 'v1') {
                        $signatures[] = $subparts[1];
                    }
                }
            }

            if (!$timestamp || empty($signatures)) {
                return response()->json(['message' => 'Invalid Stripe Signature format'], 400);
            }

            // Reject if the signature timestamp is older than 5 minutes to prevent replay attacks
            if (abs(time() - $timestamp) > 300) {
                return response()->json(['message' => 'Stripe Webhook signature expired (replay attack protection)'], 400);
            }

            $signedPayload = "{$timestamp}.{$payload}";
            $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

            $match = false;
            foreach ($signatures as $signature) {
                if (hash_equals($expectedSignature, $signature)) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                return response()->json(['message' => 'Stripe signature validation failed'], 403);
            }
        }

        // Process webhook event payload
        $event = json_decode($payload, true);
        if (!$event) {
            return response()->json(['message' => 'Malformed Stripe webhook JSON payload'], 400);
        }

        // Execute Stripe upgrade logic for checkout.session.completed
        if ($event['type'] === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            
            // Check metadata or client_reference_id for the transaction ID
            $transactionId = $session['client_reference_id'] ?? ($session['metadata']['transaction_id'] ?? null);
            
            if ($transactionId) {
                $transaction = Transaction::find($transactionId);
                
                if ($transaction && $transaction->status !== 'paid') {
                    $transaction->update(['status' => 'paid']);
                    
                    $user = $transaction->user;
                    $currentExpiresAt = $user->subscription ? $user->subscription->expires_at : now();
                    
                    if ($currentExpiresAt->isPast()) {
                        $currentExpiresAt = now();
                    }

                    $planName = 'Career OS Pro';
                    if ($transaction->plan_id) {
                        $plan = \App\Models\Plan::where('identifier', $transaction->plan_id)->first();
                        if ($plan) $planName = $plan->name;
                    }

                    $user->subscriptions()->create([
                        'plan_name' => $planName,
                        'expires_at' => clone $currentExpiresAt->addDays(30),
                        'status' => 'active'
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Stripe Webhook Processed']);
    }

    public function sslCommerzSuccess(Request $request)
    {
        $this->activateSslCommerzTransaction($request);
        return Redirect::to(env('FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment=success');
    }

    public function sslCommerzFail(Request $request)
    {
        return Redirect::to(env('FRONTEND_URL', 'http://localhost:5173') . '/billing?payment=fail');
    }

    public function sslCommerzCancel(Request $request)
    {
        return Redirect::to(env('FRONTEND_URL', 'http://localhost:5173') . '/billing?payment=cancel');
    }

    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->get('session_id');
        if ($sessionId) {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            try {
                $session = \Stripe\Checkout\Session::retrieve($sessionId);
                if ($session->payment_status === 'paid') {
                    $transactionId = $session->client_reference_id ?? ($session->metadata['transaction_id'] ?? null);
                    if ($transactionId) {
                        $transaction = Transaction::find($transactionId);
                        
                        if ($transaction && $transaction->status !== 'paid') {
                            $transaction->update(['status' => 'paid']);
                            
                            $user = $transaction->user;
                            $currentExpiresAt = $user->subscription ? $user->subscription->expires_at : now();
                            if ($currentExpiresAt->isPast()) $currentExpiresAt = now();

                            $planName = 'Career OS Pro';
                            if ($transaction->plan_id) {
                                $plan = \App\Models\Plan::where('identifier', $transaction->plan_id)->first();
                                if ($plan) $planName = $plan->name;
                            }

                            $user->subscriptions()->create([
                                'plan_name' => $planName,
                                'expires_at' => clone $currentExpiresAt->addDays(30),
                                'status' => 'active'
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Stripe session synchronous validation failed: ' . $e->getMessage());
            }
        }
        return Redirect::to(env('FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment=success');
    }

    public function stripeCancel(Request $request)
    {
        return Redirect::to(env('FRONTEND_URL', 'http://localhost:5173') . '/billing?payment=cancel');
    }
}
