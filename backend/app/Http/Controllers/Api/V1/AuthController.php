<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and create their first business + head-office branch.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'en',
                'timezone' => $data['timezone'] ?? 'Africa/Accra',
            ]);

            $business = Business::create([
                'name' => $data['business_name'],
                'phone' => $data['business_phone'] ?? $data['phone'] ?? null,
                'email' => $data['business_email'] ?? $data['email'],
                'country' => $data['country'] ?? 'GH',
                'currency' => $data['currency'] ?? 'GHS',
                'timezone' => $data['timezone'] ?? 'Africa/Accra',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'multi_branch_enabled' => false,
            ]);

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => $data['branch_name'] ?? 'Head Office',
                'code' => 'HO',
                'is_head_office' => true,
                'status' => 'active',
                'manager_id' => $user->id,
            ]);

            $business->users()->attach($user->id, [
                'branch_id' => $branch->id,
                'is_owner' => true,
                'is_active' => true,
            ]);

            // Assign owner role (will be seeded later; for now we rely on is_owner pivot)
            // Role assignment happens after roles are seeded in a later phase.

            return compact('user', 'business', 'branch');
        });

        event(new Registered($result['user']));

        $token = $result['user']->createToken(
            $request->input('device_name', 'api-token'),
            ['*']
        )->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Business created and trial started.',
            'data' => [
                'user' => new UserResource($result['user']),
                'business' => [
                    'id' => $result['business']->id,
                    'name' => $result['business']->name,
                    'currency' => $result['business']->currency,
                    'status' => $result['business']->status,
                    'trial_ends_at' => $result['business']->trial_ends_at?->toIso8601String(),
                ],
                'branch' => [
                    'id' => $result['branch']->id,
                    'name' => $result['branch']->name,
                    'is_head_office' => true,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Contact support.'],
            ]);
        }

        // Revoke old tokens for this device name if provided (optional security)
        if ($request->filled('device_name')) {
            $user->tokens()->where('name', $request->input('device_name'))->delete();
        }

        $token = $user->createToken(
            $request->input('device_name', 'api-token'),
            ['*']
        )->plainTextToken;

        $businesses = $user->businesses()
            ->wherePivot('is_active', true)
            ->get()
            ->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'currency' => $b->currency,
                'status' => $b->status,
                'is_owner' => (bool) $b->pivot->is_owner,
                'branch_id' => $b->pivot->branch_id,
            ]);

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => new UserResource($user),
                'businesses' => $businesses,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices.',
        ]);
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $businesses = $user->businesses()
            ->wherePivot('is_active', true)
            ->get()
            ->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'currency' => $b->currency,
                'status' => $b->status,
                'is_owner' => (bool) $b->pivot->is_owner,
                'branch_id' => $b->pivot->branch_id,
            ]);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'businesses' => $businesses,
            ],
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens for security
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }
}
