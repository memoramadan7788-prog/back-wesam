<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(Request $request)
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

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check(
            $request->password,
            $admin->password
        )) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $admin->createToken('admin-api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'token_type' => 'Bearer',
            'access_token' => $token,
        ], 200);
    }

    public function me(Request $request)
    {
        $admin = $request->user();

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ], 200);
    }

    public function logout(Request $request)
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

public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
    ]);

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


public function resetPassword(Request $request)
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

            // Revoke all existing Sanctum tokens.
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
