<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Interview;
use App\Models\InterviewEvaluation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InterviewPolicy
{
    /**
     * Determine whether a candidate can view their interview.
     * Enforces candidate ownership via Query Builder traversal:
     * interviews.application_id -> applications.id -> applications.candidate_id -> candidate_profiles.id -> candidate_profiles.user_id
     */
    public function viewCandidate(Authenticatable $user, Interview $interview): Response
    {
        if ($user instanceof Admin) {
            return Response::deny('Admins must view interviews through the administrative portal.');
        }

        if (!Schema::hasTable('applications') || !Schema::hasTable('candidate_profiles')) {
            return Response::deny('Candidate application context is unavailable.');
        }

        $isOwner = DB::table('applications')
            ->join('candidate_profiles', 'candidate_profiles.id', '=', 'applications.candidate_id')
            ->where('applications.id', $interview->application_id)
            ->where('candidate_profiles.user_id', $user->getAuthIdentifier())
            ->exists();

        if ($isOwner) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view this interview.');
    }

    /**
     * Determine whether an interviewer or admin can view an assigned interview.
     * Enforced through the interview's actual bookings or administrative privileges.
     */
    public function viewInterviewer(Authenticatable $user, Interview $interview): Response
    {
        if ($user instanceof Admin) {
            return Response::allow();
        }

        $isAssigned = $interview->bookings()
            ->where('interviewer_id', $user->getAuthIdentifier())
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        $isRecruiter = $this->hasRecruitmentPrivilege($user);

        return ($isAssigned || $isRecruiter)
            ? Response::allow()
            : Response::deny('You are not assigned to this interview panel.');
    }

    /**
     * Determine whether a user can schedule an interview.
     * Restricts platform Admins (separate identity table) and Candidates.
     * Permits authorized company HR/Recruiter users (User model).
     */
    public function schedule(Authenticatable $user): Response
    {
        if ($user instanceof Admin) {
            return Response::deny('Platform administrators are not authorized to directly schedule operational interviews. This action must be performed by authorized company recruitment personnel.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates are not authorized to schedule interviews.');
        }

        if ($this->hasRecruitmentPrivilege($user)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to schedule interviews.');
    }

    /**
     * Determine whether a user can reschedule an interview.
     * Denies platform Admins and Candidates.
     * Only assigned interviewers or authorized company recruitment personnel may reschedule.
     */
    public function reschedule(Authenticatable $user, Interview $interview): Response
    {
        if (!$interview->canBeRescheduled()) {
            return Response::deny('This interview cannot be rescheduled in its current status.');
        }

        if ($user instanceof Admin) {
            return Response::deny('Platform administrators are not authorized to reschedule operational interview bookings.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates are not authorized to reschedule interviews.');
        }

        $isAssigned = $interview->bookings()
            ->where('interviewer_id', $user->getAuthIdentifier())
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        $isRecruiter = $this->hasRecruitmentPrivilege($user);

        return ($isAssigned || $isRecruiter)
            ? Response::allow()
            : Response::deny('You are not authorized to reschedule this interview.');
    }

    /**
     * Determine whether a user can cancel an interview.
     * Denies platform Admins and Candidates.
     * Only assigned interviewers or authorized company recruitment personnel may cancel.
     */
    public function cancel(Authenticatable $user, Interview $interview): Response
    {
        if (!$interview->canBeCancelled()) {
            return Response::deny('This interview cannot be cancelled in its current status.');
        }

        if ($user instanceof Admin) {
            return Response::deny('Platform administrators are not authorized to cancel operational interview bookings. Cancellations must be performed by an assigned interviewer or company HR.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates are not authorized to cancel interviews.');
        }

        $isAssigned = $interview->bookings()
            ->where('interviewer_id', $user->getAuthIdentifier())
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        $isRecruiter = $this->hasRecruitmentPrivilege($user);

        return ($isAssigned || $isRecruiter)
            ? Response::allow()
            : Response::deny('You are not authorized to cancel this interview.');
    }

    /**
     * Determine whether a user can submit an evaluation.
     */
    public function evaluate(Authenticatable $user, Interview $interview): Response
    {
        if (!$interview->canBeEvaluated()) {
            return Response::deny('Cancelled interviews cannot be evaluated.');
        }

        if ($user instanceof Admin) {
            return Response::deny('Platform administrators cannot submit interview evaluations.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot submit interview evaluations.');
        }

        $isAssigned = $interview->bookings()
            ->where('interviewer_id', $user->getAuthIdentifier())
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        $isRecruiter = $this->hasRecruitmentPrivilege($user);

        return ($isAssigned || $isRecruiter)
            ? Response::allow()
            : Response::deny('Only assigned interviewers or authorized company recruitment personnel can submit evaluations for this interview.');
    }

    /**
     * Determine whether a user can publish an evaluation.
     * Authorized if the user is an assigned interviewer for the interview,
     * the evaluator who submitted the evaluation, authorized company HR, or a platform Admin.
     */
    public function publishEvaluation(Authenticatable $user, Interview $interview, ?InterviewEvaluation $evaluation = null): Response
    {
        if (strtolower((string) $interview->status) === 'cancelled') {
            return Response::deny('Cannot publish evaluations for a cancelled interview.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot publish evaluations.');
        }

        if ($user instanceof Admin) {
            return Response::allow();
        }

        $isAssigned = $interview->bookings()
            ->where('interviewer_id', $user->getAuthIdentifier())
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        $isEvaluator = $evaluation && (int) $evaluation->evaluator_id === (int) $user->getAuthIdentifier();

        $isRecruiter = $this->hasRecruitmentPrivilege($user);

        return ($isAssigned || $isEvaluator || $isRecruiter)
            ? Response::allow()
            : Response::deny('You are not authorized to publish this evaluation.');
    }

    /**
     * Helper to verify company recruitment privilege for operational scheduling and cancellations.
     * Restricts platform Admins (separate identity table) and Candidates.
     */
    public function hasRecruitmentPrivilege(Authenticatable $user): bool
    {
        if ($user instanceof Admin) {
            return false;
        }

        if ($this->isCandidate($user)) {
            return false;
        }

        if (isset($user->role) && in_array(strtolower((string) $user->role), ['hr', 'recruiter', 'hiring_manager', 'admin'], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('hr') || $user->hasRole('recruiter') || $user->hasRole('admin'))) {
            return true;
        }

        if (Schema::hasTable('user_roles') && Schema::hasTable('roles')) {
            return DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $user->getAuthIdentifier())
                ->whereIn(DB::raw('LOWER(roles.name)'), ['hr', 'recruiter', 'hiring_manager', 'admin'])
                ->exists();
        }

        if (Schema::hasTable('company_employees')) {
            return DB::table('company_employees')
                ->where('user_id', $user->getAuthIdentifier())
                ->whereIn('status', ['approved', 'active'])
                ->exists();
        }

        return false;
    }

    /**
     * Helper to verify administrative privilege safely across both User and Admin authenticatables.
     */
    protected function hasAdminPrivilege(Authenticatable $user): bool
    {
        if ($user instanceof Admin) {
            return true;
        }

        return $this->hasRecruitmentPrivilege($user);
    }

    /**
     * Helper to determine whether the authenticatable user is a Candidate.
     */
    protected function isCandidate(Authenticatable $user): bool
    {
        if ($user instanceof Admin) {
            return false;
        }

        if (isset($user->role) && strtolower((string) $user->role) === 'candidate') {
            return true;
        }

        if (Schema::hasTable('candidate_profiles')) {
            return DB::table('candidate_profiles')
                ->where('user_id', $user->getAuthIdentifier())
                ->exists();
        }

        return false;
    }
}

