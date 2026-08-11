<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->is_active) {
            $request->user()->currentAccessToken()?->delete(); // Revoke token immediately
            $reason = $request->user()->settings['suspension_reason'] ?? 'Please contact standard support.';
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Reason: ' . $reason,
                'require_logout' => true
            ], 403);
        }

        return $next($request);
    }
}
