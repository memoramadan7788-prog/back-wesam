<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Usage: middleware('permission:jobs.create') or middleware('permission:jobs.create,jobs.update')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user instanceof Admin) {
            foreach ($permissions as $permission) {
                if ($user->hasPermission($permission)) {
                    return $next($request);
                }
            }

            return response()->json([
                'message' => 'You do not have the required admin permission to perform this action.',
            ], 403);
        }

        // Legacy User admins remain supported while existing data is migrated.
        if ($user->account_type === 'admin') {
            return $next($request);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        $companyId = $user->resolveCompanyId();

        $hasValidPermission = false;
        foreach ($permissions as $perm) {
            if ($user->hasPermissionForCompany($perm, $companyId)) {
                $hasValidPermission = true;
                break;
            }
        }

        if (! $hasValidPermission) {
            return response()->json([
                'message' => 'You do not have the required permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}

