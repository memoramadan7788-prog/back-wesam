<?php

namespace App\Http\Middleware;

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

        // Admin users (account_type = admin) bypass permission checks
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

