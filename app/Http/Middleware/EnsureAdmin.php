<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user is an admin (account_type = 'admin').
 *
 * Phase 0.5: Consolidated from checking Admin model instance to checking
 * the User model's account_type field, so the separate admins table is no longer needed.
 */
class EnsureAdmin
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user || $user->account_type !== 'admin') {
            return response()->json([
                'message' => 'Forbidden. Admin access only.',
            ], 403);
        }

        return $next($request);
    }
}
