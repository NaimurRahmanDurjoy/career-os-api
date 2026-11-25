<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Mail\EmailVerificationOtpMail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', 
        ]);

        // Generate 6-digit cryptographically random OTP
        $otp = sprintf('%06d', random_int(100000, 999999));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_code' => $otp,
            'verification_code_expires_at' => now()->addMinutes(15),
            'settings' => ['theme' => 'light'] 
        ]);

        app()->terminating(function () use ($user, $otp) {
            try {
                Mail::to($user->email)->send(new EmailVerificationOtpMail($user, $otp));
            } catch (\Exception $e) {
                \Log::error('Registration OTP Mail failed: ' . $e->getMessage());
            }
        });

        return response()->json([
            'requires_verification' => true,
            'email' => $user->email,
            'message' => 'Verification code sent to your email. Please verify to access your workspace.'
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        if (!$user->is_active) {
            $reason = $user->settings['suspension_reason'] ?? 'Please contact support.';
            return response()->json([
                'message' => 'Your account has been suspended. Reason: ' . $reason,
                'require_logout' => true
            ], 403);
        }

        // Strict Email Verification Check
        if (!$user->email_verified_at) {
            // Re-generate OTP if expired
            if (!$user->verification_code || !$user->verification_code_expires_at || $user->verification_code_expires_at->isPast()) {
                $newOtp = sprintf('%06d', random_int(100000, 999999));
                $user->update([
                    'verification_code' => $newOtp,
                    'verification_code_expires_at' => now()->addMinutes(15),
                ]);

                app()->terminating(function () use ($user, $newOtp) {
                    try {
                        Mail::to($user->email)->send(new EmailVerificationOtpMail($user, $newOtp));
                    } catch (\Exception $e) {
                        \Log::error('Resend OTP on login failed: ' . $e->getMessage());
                    }
                });
            }

            return response()->json([
                'requires_verification' => true,
                'email' => $user->email,
                'message' => 'Your email is not verified yet. A verification code has been sent to your email.'
            ], 403);
        }

        // if the user is authenticated, delete all previous tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ], 200);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|string',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->has('avatar')) {
            $settings = $user->settings ?? [];
            $settings['avatar'] = $request->avatar;
            $user->settings = $settings;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ], 200);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The provided current password does not match our records.'
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully'
        ], 200);
    }

    public function updateApiKeys(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'openai_key' => 'nullable|string',
            'gemini_key' => 'nullable|string',
            'groq_key' => 'nullable|string',
        ]);

        $customKeys = $user->custom_api_keys ?? [];
        $customKeys['openai'] = $request->openai_key;
        $customKeys['gemini'] = $request->gemini_key;
        $customKeys['groq'] = $request->groq_key;

        $user->custom_api_keys = $customKeys;
        $user->save();

        return response()->json([
            'message' => 'AI Provider Integrations updated successfully',
            'user' => $user
        ], 200);
    }

    /**
     * Verify email with 6-digit OTP code.
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->email_verified_at) {
            // Already verified, generate token and proceed
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Email already verified.',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        }

        if ($user->verification_code !== $request->otp) {
            return response()->json([
                'message' => 'Invalid verification code. Please check your inbox.'
            ], 422);
        }

        if ($user->verification_code_expires_at && $user->verification_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'Verification code has expired. Please click "Resend Code".'
            ], 422);
        }

        // Successfully verified
        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->save();

        // Send welcome email after email is genuinely verified
        app()->terminating(function () use ($user) {
            try {
                Mail::to($user->email)->send(new WelcomeEmail($user));
            } catch (\Exception $e) {
                // Silently ignore mail errors
            }
        });

        // Issue token for instant workspace entry
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email successfully verified! Welcome to Career OS.',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Resend verification OTP code to user's email.
     */
    public function resendVerificationOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        $otp = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'verification_code' => $otp,
            'verification_code_expires_at' => now()->addMinutes(15),
        ]);

        app()->terminating(function () use ($user, $otp) {
            try {
                Mail::to($user->email)->send(new EmailVerificationOtpMail($user, $otp));
            } catch (\Exception $e) {
                \Log::error('Resend verification OTP failed: ' . $e->getMessage());
            }
        });

        return response()->json([
            'message' => 'A new 6-digit verification code has been sent to your email.'
        ], 200);
    }
}