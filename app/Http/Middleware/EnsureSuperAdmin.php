<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin || ! $admin->isSuperAdmin()) {
            return response()->json([
                'message' => 'Forbidden. Super Admin access only.',
            ], 403);
        }

        return $next($request);
    }
}
