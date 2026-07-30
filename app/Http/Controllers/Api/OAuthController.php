<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * Redirect the user to the provider's authentication page.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect($provider)
    {
        // Front-end directly navigates here: window.location.href = ...
        // So we return a standard redirect to Google.
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Handle the callback from the provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback($provider)
    {
        try {
            // Frontend sends the `code` and `state` as query parameters in this API call.
            $socialUser = Socialite::driver($provider)->stateless()->user();
            
            // Find or create the user
            $user = User::where('provider', $provider)
                        ->where('provider_id', $socialUser->getId())
                        ->first();
                        
            if (!$user) {
                // Check if email exists
                $user = User::where('email', $socialUser->getEmail())->first();
                
                if ($user) {
                    // Link to existing account
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $socialUser->getName() ?? 'User',
                        'email' => $socialUser->getEmail(),
                        'password' => null, // No password for OAuth users
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'settings' => ['theme' => 'light']
                    ]);
                }
            }
            
            // Create a Sanctum token
            // Delete old tokens?
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to authenticate via OAuth',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
