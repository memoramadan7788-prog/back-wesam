<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCandidateRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
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
            'user'    => new UserResource($user->load(['roles', 'candidateProfile', 'accountStatus'])),
            'token'   => $token,
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
            'user'    => new UserResource($user->load(['roles', 'accountStatus'])),
            'company' => $data['company'],
            'token'   => $token,
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

        if ($user->account_status_id === AccountStatus::BLOCKED || $user->account_status_id === AccountStatus::DELETED) {
            return response()->json([
                'message' => 'This account has been suspended or deleted. Please contact support.',
            ], 403);
        }

        $abilities = [$user->account_type];
        $token = $user->createToken('auth-token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => new UserResource($user->load(['roles', 'candidateProfile', 'accountStatus'])),
            'token'   => $token,
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
     */
    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['roles', 'candidateProfile', 'accountStatus'])
        );
    }
}
