<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     *
     * Platform KPIs for the super admin overview page.
     */
    public function index(): JsonResponse
    {
        $now = Carbon::now();
        $last30 = $now->copy()->subDays(30);
        $prev30 = $now->copy()->subDays(60);

        // ---- users ----
        $usersByType = User::query()
            ->selectRaw('account_type, COUNT(*) as total')
            ->groupBy('account_type')
            ->pluck('total', 'account_type');

        // ---- companies ----
        $companiesByStatus = Company::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // ---- 6 month trend (users + applications) ----
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();

            $trend[] = [
                'month'        => $start->format('Y-m'),
                'label'        => $start->translatedFormat('M'),
                'users'        => User::whereBetween('created_at', [$start, $end])->count(),
                'applications' => Application::whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        return response()->json([
            'data' => [
                'users' => [
                    'total'    => User::count(),
                    'new_30d'  => User::where('created_at', '>=', $last30)->count(),
                    'prev_30d' => User::whereBetween('created_at', [$prev30, $last30])->count(),
                    'inactive' => User::whereIn('account_status_id', [
                        AccountStatus::DEACTIVATED,
                        AccountStatus::BLOCKED,
                    ])->count(),
                    'by_type'  => [
                        'candidate' => (int) ($usersByType['candidate'] ?? 0),
                        'company'   => (int) ($usersByType['company'] ?? 0),
                        'admin'     => (int) ($usersByType['admin'] ?? 0),
                    ],
                ],
                'companies' => [
                    'total'     => Company::count(),
                    'new_30d'   => Company::where('created_at', '>=', $last30)->count(),
                    'by_status' => [
                        'pending'   => (int) ($companiesByStatus['pending'] ?? 0),
                        'approved'  => (int) ($companiesByStatus['approved'] ?? 0),
                        'rejected'  => (int) ($companiesByStatus['rejected'] ?? 0),
                        'suspended' => (int) ($companiesByStatus['suspended'] ?? 0),
                    ],
                ],
                'jobs' => [
                    'total'     => Job::count(),
                    'published' => Job::where('status', 'published')->count(),
                ],
                'applications' => [
                    'total'    => Application::count(),
                    'last_30d' => Application::where('created_at', '>=', $last30)->count(),
                    'prev_30d' => Application::whereBetween('created_at', [$prev30, $last30])->count(),
                ],
                'interviews' => [
                    'scheduled' => Interview::where('status', 'scheduled')->count(),
                ],
                'trend' => $trend,
                'pending_companies' => Company::where('status', 'pending')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'name', 'industry', 'contact_email', 'created_at'])
                    ->map(fn ($c) => [
                        'id'            => $c->id,
                        'name'          => $c->name,
                        'industry'      => $c->industry,
                        'contact_email' => $c->contact_email,
                        'created_at'    => $c->created_at?->toIso8601String(),
                    ]),
                'recent_activity' => AuditLog::with('user')
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn ($log) => [
                        'id'             => $log->id,
                        'action'         => $log->action,
                        'auditable_type' => class_basename($log->auditable_type),
                        'user'           => $log->user?->name,
                        'created_at'     => $log->created_at?->toIso8601String(),
                    ]),
                'generated_at' => $now->toIso8601String(),
            ],
        ]);
    }
}
