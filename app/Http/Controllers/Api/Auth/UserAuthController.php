<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCandidateRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\RegisterEmployeeRequest;
use App\Http\Resources\UserResource;
use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class UserAuthController extends Controller
{
    /**
     * POST /api/auth/register/candidate
     */
    public function registerCandidate(RegisterCandidateRequest $request, EmailVerificationService $verificationService): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => Hash::make($validated['password']),
                'account_type'      => 'candidate',
                'account_status_id' => AccountStatus::ACTIVE,
            ]);

            CandidateProfile::create([
                'user_id'  => $user->id,
                'phone'    => $validated['phone'] ?? null,
                'headline' => $validated['headline'] ?? null,
            ]);

            $candidateRole = Role::where('name', 'candidate')->first();
            if ($candidateRole) {
                $user->roles()->attach($candidateRole->id, ['assigned_at' => now()]);
            }

            return $user;
        });

        $token = $user->createToken('candidate-token', ['candidate'])->plainTextToken;
        $verificationService->issueAndSend($user, false);

        return response()->json([
            'message' => 'Candidate registered successfully.',
            'user'    => new UserResource($user->load(['roles.permissions', 'candidateProfile', 'accountStatus'])),
            'access_token' => $token,
            'token'   => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/auth/register/company
     */
    public function registerCompany(RegisterCompanyRequest $request, EmailVerificationService $verificationService): JsonResponse
    {
        $validated = $request->validated();

        $data = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => Hash::make($validated['password']),
                'account_type'      => 'company',
                'account_status_id' => AccountStatus::ACTIVE,
            ]);

            $company = Company::create([
                'name'              => $validated['company_name'],
                'industry'          => $validated['industry'] ?? null,
                'website'           => $validated['website'] ?? null,
                'contact_email'     => $validated['contact_email'] ?? null,
                'contact_phone'     => $validated['contact_phone'] ?? null,
                'address'           => $validated['address'] ?? null,
                'status'            => 'pending',
                'account_status_id' => AccountStatus::ACTIVE,
                'owner_user_id'     => $user->id,
            ]);

            $companyRole = Role::where('name', 'company_admin')->first();
            if ($companyRole) {
                $user->roles()->attach($companyRole->id, [
                    'company_id'  => $company->id,
                    'assigned_at' => now(),
                ]);
            }

            return ['user' => $user, 'company' => $company];
        });

        $user = $data['user'];
        $token = $user->createToken('company-token', ['company'])->plainTextToken;
        $verificationService->issueAndSend($user, false);

        return response()->json([
            'message' => 'Company registered successfully. Account pending review.',
            'user'    => new UserResource($user->load(['roles.permissions', 'accountStatus'])),
            'company' => $data['company'],
            'access_token' => $token,
            'token'   => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/auth/register/employee
     */
    public function registerEmployee(RegisterEmployeeRequest $request, EmailVerificationService $verificationService): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'account_type'      => 'company',
            'account_status_id' => AccountStatus::ACTIVE,
        ]);

        $token = $user->createToken('employee-token', ['company'])->plainTextToken;
        $verificationService->issueAndSend($user, false);

        return response()->json([
            'message'      => 'Employee account registered successfully. Please submit a join request to your company.',
            'user'         => new UserResource($user->load(['roles.permissions', 'accountStatus'])),
            'token'        => $token,
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->account_status_id !== AccountStatus::ACTIVE) {
            return response()->json([
                'message' => 'This account is inactive, suspended, or deleted. Please contact support.',
            ], 403);
        }

        $abilities = [$user->account_type];
        $token = $user->createToken('auth-token', $abilities)->plainTextToken;

        return response()->json([
            'message'      => 'Login successful.',
            'user'         => new UserResource($user->load(['roles.permissions', 'candidateProfile', 'accountStatus', 'ownedCompanies', 'companyEmployee.company'])),
            'token'        => $token,
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 200);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ], 200);
    }

    /**
     * GET /api/auth/me
     *
     * Returns user + company + roles + aggregated permissions (Phase 0.2).
     */
    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load([
                'roles.permissions',
                'candidateProfile',
                'accountStatus',
                'ownedCompanies',
                'companyEmployee.company',
            ])
        );
    }

    /**
     * POST /api/auth/email/verify
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $verification = $user?->emailVerificationCodes()
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $user || ! $verification || $verification->expires_at->isPast()
            || ! Hash::check($validated['code'], $verification->code_hash)) {
            return response()->json([
                'message' => 'The verification code is invalid or expired.',
            ], 422);
        }

        $verification->update(['consumed_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => new UserResource($user->fresh()),
        ], 200);
    }

    /**
     * POST /api/auth/email/resend
     */
    public function resendEmailVerification(Request $request, EmailVerificationService $verificationService): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $validated['email'])->first();

        if (! $user || $user->email_verified_at) {
            return response()->json([
                'message' => 'If the email is registered and unverified, a verification code will be sent.',
            ], 200);
        }

        if (! $verificationService->canResend($user)) {
            return response()->json([
                'message' => 'Too many verification requests. Please try again later.',
                'retry_after' => $verificationService->retryAfter($user),
            ], 429);
        }

        $verificationService->issueAndSend($user);

        return response()->json([
            'message' => 'If the email is registered, a verification code will be sent.',
        ], 200);
    }

    /**
     * PATCH /api/auth/me/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'max:10'],
            'theme' => ['sometimes', Rule::in(['system', 'light', 'dark'])],
        ]);

        if ($validated === []) {
            return response()->json(['message' => 'At least one preference is required.'], 422);
        }

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Preferences updated successfully.',
            'preferences' => [
                'locale' => $user->locale,
                'theme' => $user->theme,
            ],
        ], 200);
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'If the email is registered, a password reset link will be sent.',
            ], 200);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        return response()->json([
            'message' => 'Unable to send the password reset link.',
        ], 400);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only(
                'email',
                'token',
                'password',
                'password_confirmation'
            ),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Revoke all existing Sanctum tokens.
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ], 200);
        }

        return response()->json([
            'message' => 'Invalid or expired password reset token.',
        ], 400);
    }

    /**
     * PUT /api/candidate/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'      => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password does not match our records.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->new_password),
        ])->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ], 200);
    }

    /**
     * DELETE /api/auth/account
     *
     * User accounts are soft-deleted and all active API tokens are revoked.
     * Platform admins use the dedicated admin lifecycle instead.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Admin) {
            return response()->json([
                'message' => 'Platform admin accounts cannot be deleted from the user account endpoint.',
            ], 403);
        }

        $user->forceFill([
            'account_status_id' => AccountStatus::DELETED,
        ])->save();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ], 200);
    }
}
