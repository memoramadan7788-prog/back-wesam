<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCandidateRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\RegisterEmployeeRequest;
use App\Http\Resources\UserResource;
use App\Models\AccountStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class UserAuthController extends Controller
{
    /**
     * POST /api/auth/register/candidate
     */
    public function registerCandidate(RegisterCandidateRequest $request): JsonResponse
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
    public function registerCompany(RegisterCompanyRequest $request): JsonResponse
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
    public function registerEmployee(RegisterEmployeeRequest $request): JsonResponse
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
}
