<?php

namespace App\Http\Middleware;

use App\Models\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompany
{
    /**
     * Ensure the authenticated user belongs to an active, approved company.
     * Prevents access to company endpoints if the company is pending, rejected, or suspended.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Allow join requests without belonging to an active company
        if ($request->is('*company/join-requests*') && $request->isMethod('POST')) {
            return $next($request);
        }

        // Platform admin handling
        if ($user->account_type === 'admin') {
            $companyId = $user->resolveCompanyId();
            if (!$companyId && !$request->is('*/join-requests*')) {
                // For admin accessing company endpoints, company_id is required (Point 7)
                return response()->json([
                    'message' => 'For platform administrators, an explicit company_id parameter or X-Company-Id header is required to perform company-scoped operations.',
                ], 422);
            }
            return $next($request);
        }

        $company = $user->resolveCompany();

        if (! $company) {
            return response()->json([
                'message' => 'You are not associated with any company.',
            ], 403);
        }

        if ($company->status === 'pending') {
            return response()->json([
                'message' => 'Your company account is pending administrative approval. You cannot access company operations until your account is approved.',
            ], 403);
        }

        if ($company->status === 'rejected') {
            return response()->json([
                'message' => 'Your company registration has been rejected. Company operations are disabled.',
            ], 403);
        }

        if ($company->status === 'suspended') {
            return response()->json([
                'message' => 'Your company account has been suspended. Company operations are disabled.',
            ], 403);
        }

        if ($company->account_status_id && $company->account_status_id !== AccountStatus::ACTIVE) {
            return response()->json([
                'message' => 'Your company account is inactive or blocked.',
            ], 403);
        }

        return $next($request);
    }
}
