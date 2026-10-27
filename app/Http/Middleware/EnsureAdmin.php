<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated principal is a platform admin.
 * Legacy users with account_type=admin remain accepted during migration.
 */
class EnsureAdmin
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user || (! $user instanceof Admin && $user->account_type !== 'admin')) {
            return response()->json([
                'message' => 'Forbidden. Admin access only.',
            ], 403);
        }

        return $next($request);
    }
}
