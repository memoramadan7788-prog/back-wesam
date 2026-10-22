<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InterviewerInterviewResource;
use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class InterviewerInterviewController extends Controller
{
    /**
     * List all interviews assigned to the authenticated interviewer.
     * Prevents N+1 queries by bulk-resolving candidate & CV metadata for the page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $interviewerId = $request->user()->id;

        $query = Interview::query()
            ->with(['bookings.interviewer', 'bookings.availability', 'evaluations.evaluator'])
            ->forInterviewer($interviewerId);

        if ($request->input('filter') === 'upcoming') {
            $query->upcoming();
        } elseif ($request->input('filter') === 'past') {
            $query->past();
        }

        if ($status = $request->input('status')) {
            $query->filterByStatus($status);
        }

        $interviews = $query->orderBy('id', 'desc')
            ->paginate((int) $request->input('per_page', 15));

        // Bulk load candidate & application CV metadata to prevent N+1 queries
        $applicationIds = $interviews->pluck('application_id')->filter()->unique()->all();
        if (!empty($applicationIds) && Schema::hasTable('applications')) {
            $candidateMap = DB::table('applications')
                ->when(Schema::hasTable('candidate_profiles'), fn($q) => $q->leftJoin('candidate_profiles', 'candidate_profiles.id', '=', 'applications.candidate_id'))
                ->when(Schema::hasTable('users') && Schema::hasTable('candidate_profiles'), fn($q) => $q->leftJoin('users', 'users.id', '=', 'candidate_profiles.user_id'))
                ->when(Schema::hasTable('candidate_cvs'), fn($q) => $q->leftJoin('candidate_cvs', 'candidate_cvs.id', '=', 'applications.cv_id'))
                ->whereIn('applications.id', $applicationIds)
                ->select([
                    'applications.id as application_id',
                    'applications.candidate_id',
                    'applications.cv_id',
                    (Schema::hasTable('users') && Schema::hasColumn('users', 'id')) ? 'users.id as user_id' : DB::raw('NULL as user_id'),
                    (Schema::hasTable('users') && Schema::hasColumn('users', 'name')) ? 'users.name as candidate_name' : DB::raw('NULL as candidate_name'),
                    (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) ? 'users.email as candidate_email' : DB::raw('NULL as candidate_email'),
                    (Schema::hasTable('candidate_profiles') && Schema::hasColumn('candidate_profiles', 'phone')) ? 'candidate_profiles.phone as candidate_phone' : DB::raw('NULL as candidate_phone'),
                    (Schema::hasTable('candidate_profiles') && Schema::hasColumn('candidate_profiles', 'headline')) ? 'candidate_profiles.headline as candidate_headline' : DB::raw('NULL as candidate_headline'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'original_filename')) ? 'candidate_cvs.original_filename as cv_filename' : DB::raw('NULL as cv_filename'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'mime_type')) ? 'candidate_cvs.mime_type as cv_mime_type' : DB::raw('NULL as cv_mime_type'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'size_bytes')) ? 'candidate_cvs.size_bytes as cv_size_bytes' : DB::raw('NULL as cv_size_bytes'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'uploaded_at')) ? 'candidate_cvs.uploaded_at as cv_uploaded_at' : DB::raw('NULL as cv_uploaded_at'),
                ])
                ->get()
                ->keyBy('application_id');

            foreach ($interviews as $interview) {
                $interview->candidateData = $candidateMap->get($interview->application_id);
            }
        }

        return InterviewerInterviewResource::collection($interviews);
    }

    /**
     * Retrieve assigned interview details.
     */
    public function show(Request $request, int $id): InterviewerInterviewResource
    {
        $interview = Interview::query()
            ->with(['bookings.interviewer', 'bookings.availability', 'evaluations.evaluator'])
            ->findOrFail($id);

        Gate::authorize('viewInterviewer', $interview);

        return new InterviewerInterviewResource($interview);
    }
}
