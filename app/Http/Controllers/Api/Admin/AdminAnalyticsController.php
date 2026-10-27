<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Offer;
use App\Models\User;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $months = min(max((int) $request->integer('months', 6), 3), 12);
        $from = $now->copy()->startOfMonth()->subMonths($months - 1);

        $applicationStatuses = $this->counts(Application::query(), 'status');
        $offerStatuses = $this->counts(Offer::query(), 'status');
        $interviewStatuses = $this->counts(Interview::query(), 'status');
        $employeeStatuses = $this->counts(CompanyEmployee::query(), 'status');
        $jobStatuses = $this->counts(Job::query(), 'status');
        $jobVisibility = $this->counts(Job::query(), 'visibility');
        $examStatuses = $this->counts(Exam::query(), 'status');
        $attemptStatuses = $this->counts(ExamAttempt::query(), 'status');

        $applicationsTotal = Application::count();
        $interviewsTotal = Interview::count();
        $offersTotal = Offer::count();

        return response()->json([
            'data' => [
                'period' => [
                    'months' => $months,
                    'from' => $from->toIso8601String(),
                    'to' => $now->toIso8601String(),
                ],
                'overview' => [
                    'users' => [
                        'total' => User::count(),
                        'active' => User::where('account_status_id', AccountStatus::ACTIVE)->count(),
                        'inactive' => User::whereIn('account_status_id', [
                            AccountStatus::DEACTIVATED,
                            AccountStatus::BLOCKED,
                        ])->count(),
                        'candidates' => User::where('account_type', 'candidate')->count(),
                        'company_accounts' => User::where('account_type', 'company')->count(),
                        'legacy_admin_accounts' => User::where('account_type', 'admin')->count(),
                        'platform_admins' => Admin::count(),
                    ],
                    'companies' => [
                        'total' => Company::count(),
                        'active_approved' => Company::where('status', 'approved')
                            ->where('account_status_id', AccountStatus::ACTIVE)
                            ->count(),
                        'pending_review' => Company::where('status', 'pending')->count(),
                        'rejected' => Company::where('status', 'rejected')->count(),
                        'suspended' => Company::where('status', 'suspended')->count(),
                    ],
                    'employees' => [
                        'total' => CompanyEmployee::count(),
                        'approved' => (int) ($employeeStatuses['approved'] ?? 0),
                        'pending' => (int) ($employeeStatuses['pending'] ?? 0),
                        'rejected' => (int) ($employeeStatuses['rejected'] ?? 0),
                        'removed' => (int) ($employeeStatuses['removed'] ?? 0),
                    ],
                    'jobs' => [
                        'total' => Job::count(),
                        'published' => (int) ($jobStatuses['published'] ?? 0),
                        'draft' => (int) ($jobStatuses['draft'] ?? 0),
                        'closed' => (int) ($jobStatuses['closed'] ?? 0),
                        'archived' => (int) ($jobStatuses['archived'] ?? 0),
                        'public' => (int) ($jobVisibility['public'] ?? 0),
                        'private' => (int) ($jobVisibility['private'] ?? 0),
                    ],
                    'applications' => [
                        'total' => $applicationsTotal,
                        'active' => $applicationsTotal
                            - (int) ($applicationStatuses['rejected'] ?? 0)
                            - (int) ($applicationStatuses['withdrawn'] ?? 0),
                        'hired' => (int) ($applicationStatuses['hired'] ?? 0),
                        'rejected' => (int) ($applicationStatuses['rejected'] ?? 0),
                    ],
                    'interviews' => [
                        'total' => $interviewsTotal,
                        'scheduled' => (int) ($interviewStatuses['scheduled'] ?? 0),
                        'completed' => (int) ($interviewStatuses['completed'] ?? 0),
                        'cancelled' => (int) ($interviewStatuses['cancelled'] ?? 0),
                    ],
                    'offers' => [
                        'total' => $offersTotal,
                        'sent' => (int) ($offerStatuses['sent'] ?? 0),
                        'accepted' => (int) ($offerStatuses['accepted'] ?? 0),
                        'rejected' => (int) ($offerStatuses['rejected'] ?? 0),
                        'acceptance_rate' => $this->rate(
                            (int) ($offerStatuses['accepted'] ?? 0),
                            $offersTotal
                        ),
                    ],
                    'exams' => [
                        'total' => Exam::count(),
                        'published' => (int) ($examStatuses['published'] ?? 0),
                        'draft' => (int) ($examStatuses['draft'] ?? 0),
                        'attempts' => ExamAttempt::count(),
                        'submitted_attempts' => (int) ($attemptStatuses['submitted'] ?? 0),
                    ],
                ],
                'breakdowns' => [
                    'users_by_type' => $this->counts(User::query(), 'account_type'),
                    'users_by_status' => $this->counts(User::query(), 'account_status_id'),
                    'companies_by_status' => $this->counts(Company::query(), 'status'),
                    'employees_by_status' => $employeeStatuses,
                    'jobs_by_status' => $jobStatuses,
                    'jobs_by_visibility' => $jobVisibility,
                    'applications_by_status' => $applicationStatuses,
                    'interviews_by_status' => $interviewStatuses,
                    'offers_by_status' => $offerStatuses,
                    'exams_by_status' => $examStatuses,
                    'exam_attempts_by_status' => $attemptStatuses,
                ],
                'recruitment_funnel' => [
                    'applications' => $applicationsTotal,
                    'shortlisted' => (int) ($applicationStatuses['shortlisted'] ?? 0),
                    'interviewing' => (int) ($applicationStatuses['interviewing'] ?? 0),
                    'offered' => (int) ($applicationStatuses['offered'] ?? 0),
                    'hired' => (int) ($applicationStatuses['hired'] ?? 0),
                    'shortlist_rate' => $this->rate(
                        (int) ($applicationStatuses['shortlisted'] ?? 0),
                        $applicationsTotal
                    ),
                    'hire_rate' => $this->rate(
                        (int) ($applicationStatuses['hired'] ?? 0),
                        $applicationsTotal
                    ),
                ],
                'trends' => [
                    'monthly' => $this->monthlyTrend($now, $months),
                ],
                'top_companies' => $this->topCompanies(),
                'top_jobs' => $this->topJobs(),
                'pending_actions' => [
                    'companies_for_review' => Company::where('status', 'pending')->count(),
                    'employee_requests' => CompanyEmployee::where('status', 'pending')->count(),
                    'applications_to_review' => Application::whereIn('status', [
                        'submitted',
                        'screening',
                    ])->count(),
                    'offers_waiting_response' => Offer::where('status', 'sent')->count(),
                    'exams_in_progress' => ExamAttempt::where('status', 'in_progress')->count(),
                ],
                'recent_activity' => AuditLog::with(['user', 'admin'])
                    ->latest()
                    ->limit(15)
                    ->get()
                    ->map(fn (AuditLog $log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'auditable_type' => class_basename($log->auditable_type),
                        'actor' => $log->admin?->name ?? $log->user?->name,
                        'actor_type' => $log->admin ? 'admin' : 'user',
                        'created_at' => $log->created_at?->toIso8601String(),
                    ])->values(),
                'generated_at' => $now->toIso8601String(),
            ],
        ]);
    }

    private function counts(Builder $query, string $column): array
    {
        return $query
            ->selectRaw("{$column}, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function rate(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0.0;
    }

    private function monthlyTrend(Carbon $now, int $months): array
    {
        $trend = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $now->copy()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();

            $trend[] = [
                'month' => $start->format('Y-m'),
                'users' => User::whereBetween('created_at', [$start, $end])->count(),
                'companies' => Company::whereBetween('created_at', [$start, $end])->count(),
                'jobs' => Job::whereBetween('created_at', [$start, $end])->count(),
                'applications' => Application::whereBetween('created_at', [$start, $end])->count(),
                'interviews' => Interview::whereBetween('created_at', [$start, $end])->count(),
                'offers' => Offer::whereBetween('created_at', [$start, $end])->count(),
                'hired' => Application::where('status', 'hired')
                    ->whereBetween('updated_at', [$start, $end])
                    ->count(),
            ];
        }

        return $trend;
    }

    private function topCompanies(): array
    {
        return Company::query()
            ->select(['companies.id', 'companies.name', 'companies.status'])
            ->withCount([
                'jobs',
                'companyEmployees',
                'exams',
            ])
            ->selectSub(
                Application::query()
                    ->join('jobs', 'jobs.id', '=', 'applications.job_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('jobs.company_id', 'companies.id'),
                'applications_count'
            )
            ->selectSub(
                Application::query()
                    ->join('jobs', 'jobs.id', '=', 'applications.job_id')
                    ->selectRaw('COUNT(*)')
                    ->where('applications.status', 'hired')
                    ->whereColumn('jobs.company_id', 'companies.id'),
                'hired_count'
            )
            ->orderByDesc('applications_count')
            ->limit(10)
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'status' => $company->status,
                'jobs' => $company->jobs_count,
                'employees' => $company->company_employees_count,
                'exams' => $company->exams_count,
                'applications' => (int) $company->applications_count,
                'hired' => (int) $company->hired_count,
            ])->all();
    }

    private function topJobs(): array
    {
        return Job::query()
            ->with('company:id,name')
            ->withCount([
                'applications',
                'applications as hired_count' => fn (Builder $query) => $query->where('status', 'hired'),
            ])
            ->orderByDesc('applications_count')
            ->limit(10)
            ->get()
            ->map(fn (Job $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->company?->name,
                'status' => $job->status,
                'visibility' => $job->visibility,
                'applications' => $job->applications_count,
                'hired' => $job->hired_count,
            ])->all();
    }
}
