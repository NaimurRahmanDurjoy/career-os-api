<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next, $limitFeature = null): Response
    {
        if ($limitFeature) {
            $limits = $request->user()->limits ?? [];
            if (isset($limits[$limitFeature]) && $limits[$limitFeature] === false) {
                return response()->json([
                    'message' => "This feature requires an upgraded subscription tier.",
                    'requires_upgrade' => true
                ], 403);
            }
        } else {
            if (! $request->user() || ! $request->user()->hasActiveSubscription()) {
                return response()->json([
                    'message' => 'This action requires an active subscription.',
                    'requires_subscription' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
