<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if ($admin instanceof Admin && $admin->isSuperAdmin()) {
            return $next($request);
        }

        // Support web frontend admin session (legacy User with account_type=admin)
        if ($admin instanceof User && $admin->account_type === 'admin') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. Super Admin access only.',
        ], 403);
    }
}
