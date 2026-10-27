<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminResource;
use App\Models\AccountStatus;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = Admin::where('email', $request->email)
            ->whereHas('roles', function ($query) {
                $query->where('admin_roles.name', 'super_admin')
                    ->where('admin_roles.scope', 'platform');
            })
            ->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($admin->account_status_id !== AccountStatus::ACTIVE) {
            return response()->json([
                'message' => 'This admin account is inactive, suspended, or deleted.',
            ], 403);
        }

        $token = $admin->createToken('admin-api', ['admin'])->plainTextToken;

        return response()->json([
            'message'      => 'Login successful.',
            'admin'        => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
            ],
            'token_type'   => 'Bearer',
            'access_token' => $token,
            'token'        => $token,
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'data' => new AdminResource($admin->load(['roles.permissions', 'accountStatus'])),
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (! $token || ! method_exists($token, 'delete')) {
            return response()->json([
                'message' => 'No revocable access token found.',
            ], 400);
        }

        $token->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ], 200);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Use the admins password broker, which stores platform admin accounts in admins.
        $status = Password::broker('admins')->sendResetLink(
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

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('admins')->reset(
            $request->only(
                'email',
                'token',
                'password',
                'password_confirmation'
            ),
            function (Admin $admin, string $password) {
                $admin->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Revoke all existing Sanctum tokens after a password reset.
                $admin->tokens()->delete();
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
}
