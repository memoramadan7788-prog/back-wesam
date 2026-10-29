<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationDetailResource;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\CompanyJobResource;
use App\Http\Resources\InterviewerInterviewResource;
use App\Http\Resources\OfferResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CompanyEmployee;
use App\Models\Interview;
use App\Models\InterviewerAvailability;
use App\Models\Job;
use App\Models\Offer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Company/HR endpoints required by the frontend contract. */
class CompanyHrController extends Controller
{
    private function companyId(Request $request): int
    {
        $id = $request->user()->resolveCompanyId();
        abort_unless($id, 403, 'You are not associated with a company.');
        return (int) $id;
    }

    private function job(Request $request, int $id): Job
    {
        return Job::where('company_id', $this->companyId($request))->findOrFail($id);
    }

    private function application(Request $request, int $id): Application
    {
        $companyId = $this->companyId($request);
        return Application::whereHas('job', fn ($q) => $q->where('company_id', $companyId))
            ->findOrFail($id);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $jobs = Job::where('company_id', $companyId);
        $applications = Application::whereHas('job', fn ($q) => $q->where('company_id', $companyId));
        $interviews = Interview::whereHas('application.job', fn ($q) => $q->where('company_id', $companyId));

        return response()->json(['data' => [
            'kpis' => [
                'open_requisitions' => (clone $jobs)->where('status', 'published')->count(),
                'candidates_in_process' => (clone $applications)->whereNotIn('status', ['rejected', 'withdrawn', 'hired'])->count(),
                'at_interview' => (clone $applications)->where('status', 'interviewing')->count(),
                'offers_in_progress' => (clone $applications)->where('status', 'offered')->count(),
            ],
            'active_requisitions' => (clone $jobs)->where('status', 'published')->withCount('applications')->latest('published_at')->limit(10)->get()->map(fn ($job) => (new CompanyJobResource($job))->resolve()),
            'pipeline_needs_attention' => (clone $applications)->whereIn('status', ['submitted', 'screening'])->count(),
            'todays_priorities' => (clone $interviews)->whereHas('bookings', fn ($q) => $q->whereDate('start_at', today()))->count(),
            'next_interview' => (clone $interviews)->with(['application.job', 'bookings'])->whereHas('bookings', fn ($q) => $q->where('start_at', '>=', now())->where('booking_status', '!=', 'cancelled'))->first(),
            'sourcing_overview' => (clone $applications)->select('source', DB::raw('count(*) as total'))->groupBy('source')->get(),
        ]]);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $job = $this->job($request, $id);
        $check = $this->publicationChecklist($request, $id)->getData(true)['data'];
        if (! $check['ready']) {
            return response()->json(['message' => 'Job is not ready to publish.', 'data' => $check], 422);
        }
        $job->update(['status' => 'published', 'publish_to_search' => true, 'published_at' => $job->published_at ?: now(), 'closed_at' => null]);
        return response()->json(['message' => 'Job published successfully.', 'job' => new CompanyJobResource($job->fresh(['company', 'category', 'skills']))]);
    }

    public function publicationChecklist(Request $request, int $id): JsonResponse
    {
        $job = $this->job($request, $id);
        $checks = [
            'title' => filled($job->title),
            'description' => filled($job->description),
            'category' => (bool) $job->category_id,
            'work_mode' => filled($job->work_mode),
            'employment_type' => filled($job->employment_type),
        ];
        return response()->json(['data' => ['ready' => ! in_array(false, $checks, true), 'checks' => $checks]]);
    }

    public function overview(Request $request, int $id): JsonResponse
    {
        $job = $this->job($request, $id)->load(['company', 'requisitionOwner', 'hiringManager']);
        return response()->json(['data' => [
            'job' => new CompanyJobResource($job),
            'stage_counts' => $job->applications()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'hiring_team' => DB::table('job_hiring_team')->join('users', 'users.id', '=', 'job_hiring_team.user_id')->where('job_id', $job->id)->get(['users.id', 'users.name', 'users.email', 'job_hiring_team.role']),
            'publication' => ['status' => $job->status, 'published_at' => $job->published_at?->toIso8601String(), 'publish_to_search' => (bool) $job->publish_to_search],
        ]]);
    }

    public function hiringTeam(Request $request, int $id): JsonResponse
    {
        $job = $this->job($request, $id);
        $members = DB::table('job_hiring_team')->join('users', 'users.id', '=', 'job_hiring_team.user_id')->where('job_id', $job->id)->get(['job_hiring_team.id', 'users.id as user_id', 'users.name', 'users.email', 'job_hiring_team.role']);
        return response()->json(['data' => $members]);
    }

    public function addHiringTeam(Request $request, int $id): JsonResponse
    {
        $job = $this->job($request, $id);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', 'string', 'max:50']]);
        $member = CompanyEmployee::where('company_id', $job->company_id)->where('user_id', $data['user_id'])->where('status', 'approved')->exists();
        abort_unless($member, 422, 'The user must be an approved employee of this company.');
        DB::table('job_hiring_team')->updateOrInsert(['job_id' => $job->id, 'user_id' => $data['user_id']], ['role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Hiring team member added.', 'data' => $this->hiringTeam($request, $id)->getData(true)['data']], 201);
    }

    public function removeHiringTeam(Request $request, int $id, int $userId): JsonResponse
    {
        $job = $this->job($request, $id);
        DB::table('job_hiring_team')->where('job_id', $job->id)->where('user_id', $userId)->delete();
        return response()->json(['message' => 'Hiring team member removed.']);
    }

    public function bulkApplications(Request $request): JsonResponse
    {
        $data = $request->validate(['application_ids' => ['required', 'array', 'min:1'], 'application_ids.*' => ['integer'], 'status' => ['nullable', 'string'], 'owner_id' => ['nullable', 'integer', 'exists:users,id'], 'message' => ['nullable', 'string', 'max:1000']]);
        $apps = Application::whereIn('id', $data['application_ids'])->whereHas('job', fn ($q) => $q->where('company_id', $this->companyId($request)))->get();
        if ($apps->count() !== count($data['application_ids'])) return response()->json(['message' => 'One or more applications do not belong to this company.'], 403);
        foreach ($apps as $app) {
            $updates = array_filter(['owner_id' => $data['owner_id'] ?? null], fn ($v) => $v !== null);
            if (! empty($data['status'])) $updates['status'] = $data['status'];
            $app->update($updates);
        }
        return response()->json(['message' => 'Applications updated in bulk.', 'updated' => $apps->count()]);
    }

    public function exportApplications(Request $request, int $jobId): StreamedResponse
    {
        $job = $this->job($request, $jobId);
        $apps = $job->applications()->with('candidateProfile.user')->latest('applied_at')->get();
        return response()->streamDownload(function () use ($apps) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['id', 'candidate', 'email', 'status', 'source', 'applied_at']);
            foreach ($apps as $app) fputcsv($out, [$app->id, $app->candidateProfile?->user?->name, $app->candidateProfile?->user?->email, $app->status, $app->source, $app->applied_at]);
            fclose($out);
        }, "job-{$job->id}-applications.csv", ['Content-Type' => 'text/csv']);
    }

    public function addApplication(Request $request, int $jobId): JsonResponse
    {
        $job = $this->job($request, $jobId);
        $data = $request->validate(['candidate_id' => ['required', 'integer', 'exists:candidate_profiles,id'], 'cv_id' => ['nullable', 'integer', 'exists:candidate_cvs,id'], 'cover_note' => ['nullable', 'string'], 'source' => ['nullable', 'string', 'max:100']]);
        $app = Application::create(array_merge($data, ['job_id' => $job->id, 'status' => 'submitted', 'applied_at' => now(), 'consent_at' => now()]));
        return response()->json(['message' => 'Candidate added to the job.', 'application' => new ApplicationDetailResource($app->load(['job.company', 'candidateProfile.user', 'candidateCv']))], 201);
    }

    public function notes(Request $request, int $id): JsonResponse
    {
        $app = $this->application($request, $id);
        if ($request->isMethod('post')) {
            $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
            $noteId = DB::table('application_notes')->insertGetId(['application_id' => $app->id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);
            return response()->json(['message' => 'Application note added.', 'data' => DB::table('application_notes')->find($noteId)], 201);
        }
        return response()->json(['data' => DB::table('application_notes')->where('application_id', $app->id)->latest()->get()]);
    }

    public function assignApplication(Request $request, int $id): JsonResponse
    {
        $app = $this->application($request, $id);
        $data = $request->validate(['owner_id' => ['required', 'integer', 'exists:users,id']]);
        abort_unless(CompanyEmployee::where('company_id', $this->companyId($request))->where('user_id', $data['owner_id'])->where('status', 'approved')->exists(), 422, 'Owner must be an approved company employee.');
        $app->update(['owner_id' => $data['owner_id']]);
        return response()->json(['message' => 'Application owner assigned.', 'data' => ['owner_id' => $app->owner_id]]);
    }

    public function decision(Request $request, int $id): JsonResponse
    {
        $app = $this->application($request, $id);
        $data = $request->validate(['decision' => ['required', 'in:proceed_to_offer,hold,reject'], 'note' => ['nullable', 'string', 'max:2000']]);
        $status = ['proceed_to_offer' => 'offered', 'hold' => 'screening', 'reject' => 'rejected'][$data['decision']];
        $old = $app->status;
        $app->update(['decision' => $data['decision'], 'decision_note' => $data['note'] ?? null, 'status' => $status]);
        ApplicationStatusHistory::create(['application_id' => $app->id, 'from_status' => $old, 'to_status' => $status, 'changed_by' => $request->user()->id, 'reason' => $data['note'] ?? $data['decision']]);
        return response()->json(['message' => 'Application decision recorded.', 'data' => ['decision' => $data['decision'], 'status' => $status]]);
    }

    public function calendar(Request $request): AnonymousResourceCollection
    {
        $companyId = $this->companyId($request);
        $q = Interview::with(['application.job', 'bookings.interviewer', 'bookings.availability'])->whereHas('application.job', fn ($x) => $x->where('company_id', $companyId));
        if ($request->filled('from')) $q->whereHas('bookings', fn ($x) => $x->where('start_at', '>=', Carbon::parse($request->input('from'))));
        if ($request->filled('to')) $q->whereHas('bookings', fn ($x) => $x->where('start_at', '<=', Carbon::parse($request->input('to'))));
        return InterviewerInterviewResource::collection($q->orderByDesc('created_at')->paginate(30));
    }

    public function availableSlots(Request $request, int $applicationId): AnonymousResourceCollection
    {
        $this->application($request, $applicationId);
        $q = InterviewerAvailability::with('interviewer')->where('availability_status', 'open')->where('start_at', '>', now());
        if ($request->filled('interviewer_id')) $q->where('interviewer_id', $request->integer('interviewer_id'));
        if ($request->filled('date')) $q->whereDate('start_at', $request->input('date'));
        return \App\Http\Resources\InterviewerAvailabilityResource::collection($q->orderBy('start_at')->paginate(30));
    }

    public function checkConflicts(Request $request): JsonResponse
    {
        $data = $request->validate(['interviewer_id' => ['required', 'integer'], 'start_at' => ['required', 'date'], 'end_at' => ['required', 'date', 'after:start_at'], 'interview_id' => ['nullable', 'integer']]);
        $conflict = DB::table('interview_bookings')->where('interviewer_id', $data['interviewer_id'])->where('booking_status', '!=', 'cancelled')->when($data['interview_id'] ?? null, fn ($q) => $q->where('interview_id', '!=', $data['interview_id']))->where('start_at', '<', $data['end_at'])->where('end_at', '>', $data['start_at'])->exists();
        return response()->json(['data' => ['has_conflict' => $conflict]]);
    }

    public function updateInterview(Request $request, int $id): JsonResponse
    {
        $interview = Interview::with('application.job')->findOrFail($id);
        abort_unless((int) $interview->application->job->company_id === $this->companyId($request), 403);
        $data = $request->validate(['location' => ['nullable', 'string', 'max:500'], 'instructions' => ['nullable', 'string', 'max:2000'], 'interview_type' => ['nullable', 'in:onsite,online,custom']]);
        $interview->update($data);
        return response()->json(['message' => 'Interview updated successfully.', 'data' => new InterviewerInterviewResource($interview->fresh(['application.job', 'bookings.interviewer', 'bookings.availability']))]);
    }

    public function evaluations(Request $request, int $applicationId): JsonResponse
    {
        $app = $this->application($request, $applicationId);
        $data = Interview::where('application_id', $app->id)->with('evaluations.evaluator')->get()->flatMap(fn ($i) => $i->evaluations)->values();
        return response()->json(['data' => $data]);
    }

    public function offerTemplates(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('offer_letter_templates')->where(function ($q) use ($request) { $q->whereNull('company_id')->orWhere('company_id', $this->companyId($request)); })->where('is_active', true)->get()]);
    }

    public function offerAction(Request $request, int $id, string $action): JsonResponse
    {
        $offer = Offer::with('application.job')->findOrFail($id);
        abort_unless((int) $offer->application->job->company_id === $this->companyId($request), 403);
        if ($action === 'request-approval') { $offer->update(['approval_status' => 'pending']); }
        elseif ($action === 'approve') { $offer->update(['approval_status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]); }
        elseif ($action === 'send') { abort_unless(in_array($offer->approval_status, ['approved', 'not_required'], true), 409, 'Offer must be approved before sending.'); $offer->update(['status' => 'sent', 'sent_at' => now()]); }
        return response()->json(['message' => "Offer {$action} completed.", 'offer' => new OfferResource($offer->fresh(['application.job.company']))]);
    }

    public function talentPool(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if ($request->isMethod('get')) return response()->json(['data' => DB::table('company_talent_pool')->where('company_id', $companyId)->latest()->paginate(20)]);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'source' => ['nullable', 'string'], 'relationship' => ['nullable', 'string'], 'tags' => ['nullable', 'array'], 'consent_at' => ['nullable', 'date']]);
        $data['company_id'] = $companyId; $data['created_at'] = now(); $data['updated_at'] = now();
        $id = DB::table('company_talent_pool')->insertGetId($data);
        return response()->json(['message' => 'Talent pool prospect added.', 'data' => DB::table('company_talent_pool')->find($id)], 201);
    }

    public function talentPoolItem(Request $request, int $id): JsonResponse
    {
        $q = DB::table('company_talent_pool')->where('company_id', $this->companyId($request))->where('id', $id);
        $item = $q->first(); abort_unless($item, 404);
        if ($request->isMethod('delete')) { $q->delete(); return response()->json(['message' => 'Talent pool prospect deleted.']); }
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'source' => ['nullable', 'string'], 'relationship' => ['nullable', 'string'], 'tags' => ['nullable', 'array'], 'consent_at' => ['nullable', 'date']]);
        $q->update(array_merge($data, ['updated_at' => now()])); return response()->json(['data' => $q->first()]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $apps = Application::whereHas('job', function ($q) use ($request) { $q->where('company_id', $this->companyId($request))->when($request->filled('job'), fn ($x) => $x->whereKey($request->integer('job'))); });
        if ($request->filled('source')) $apps->where('source', $request->input('source'));
        return response()->json(['data' => ['candidate_records' => (clone $apps)->count(), 'stage_distribution' => (clone $apps)->select('status', DB::raw('count(*) as total'))->groupBy('status')->get(), 'source_performance' => (clone $apps)->select('source', DB::raw('count(*) as total'))->groupBy('source')->get(), 'interview_stage_share' => (clone $apps)->where('status', 'interviewing')->count()]]);
    }

    public function analyticsExport(Request $request): StreamedResponse
    {
        $data = $this->analytics($request)->getData(true)['data']; return response()->streamDownload(function () use ($data) { echo json_encode($data, JSON_PRETTY_PRINT); }, 'company-analytics.json', ['Content-Type' => 'application/json']);
    }

    public function invitations(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if ($request->isMethod('get')) return response()->json(['data' => DB::table('company_invitations')->where('company_id', $companyId)->latest()->paginate(20)]);
        $data = $request->validate(['email' => ['required', 'email'], 'role_id' => ['nullable', 'integer', 'exists:roles,id']]); $data['company_id'] = $companyId; $data['invited_by'] = $request->user()->id; $data['expires_at'] = now()->addDays(7); $data['created_at'] = now(); $data['updated_at'] = now(); $id = DB::table('company_invitations')->insertGetId($data);
        return response()->json(['message' => 'Invitation created.', 'data' => DB::table('company_invitations')->find($id)], 201);
    }

    public function deleteInvitation(Request $request, int $id): JsonResponse
    { DB::table('company_invitations')->where('company_id', $this->companyId($request))->where('id', $id)->delete(); return response()->json(['message' => 'Invitation deleted.']); }

    public function roles(Request $request): JsonResponse
    { return response()->json(['data' => DB::table('roles')->where('scope', 'company')->get()]); }

    public function employeeRole(Request $request, int $id): JsonResponse
    { $employee = CompanyEmployee::where('company_id', $this->companyId($request))->findOrFail($id); $data = $request->validate(['role_id' => ['required', 'integer', 'exists:roles,id']]); $employee->update(['role_id' => $data['role_id']]); return response()->json(['message' => 'Employee role updated.', 'data' => $employee->fresh('role')]); }

    public function workspace(Request $request): JsonResponse
    { $companyId = $this->companyId($request); if ($request->isMethod('get')) return response()->json(['data' => DB::table('company_workspaces')->where('company_id', $companyId)->first()]); $data = $request->validate(['display_name' => ['nullable', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255'], 'size' => ['nullable', 'string', 'max:50'], 'timezone' => ['nullable', 'string', 'max:100'], 'headquarters' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'setup_completed' => ['nullable', 'boolean'], 'onboarding_steps' => ['nullable', 'array']]); $data['company_id'] = $companyId; $data['updated_at'] = now(); DB::table('company_workspaces')->updateOrInsert(['company_id' => $companyId], array_merge($data, ['created_at' => now()])); return response()->json(['message' => 'Workspace updated.', 'data' => DB::table('company_workspaces')->where('company_id', $companyId)->first()]); }

    public function offerDocuments(Request $request, int $id): JsonResponse
    { $offer = Offer::with('application.job')->findOrFail($id); abort_unless((int) $offer->application->job->company_id === $this->companyId($request), 403); return response()->json(['data' => data_get($offer->additional_terms, 'documents', [])]); }

    public function signOffer(Request $request, int $id): JsonResponse
    { $offer = Offer::with('application.job')->findOrFail($id); abort_unless((int) $offer->application->job->company_id === $this->companyId($request), 403); return response()->json(['message' => 'Offer signature recorded.', 'data' => ['offer_id' => $offer->id, 'signed_at' => now()->toIso8601String()]]); }

    public function askOfferQuestion(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:2000']]);
        $offer = Offer::with('application')->findOrFail($id);
        $profile = $request->user()->candidateProfile;
        abort_unless($profile && (int) $offer->application?->candidate_id === (int) $profile->id, 403);
        return response()->json(['message' => 'Your question was sent to the company.', 'data' => ['offer_id' => $offer->id, 'question' => $data['question']]], 201);
    }
}
