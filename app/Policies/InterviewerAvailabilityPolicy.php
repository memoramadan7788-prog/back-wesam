<?php

namespace App\Policies;

use App\Models\InterviewerAvailability;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InterviewerAvailabilityPolicy
{
    /**
     * Determine whether the user can list availability slots.
     */
    public function viewAny(Authenticatable $user): Response
    {
        if ($this->isPlatformAdmin($user)) {
            return Response::deny('Platform administrators manage availability via administrative controls.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot manage interviewer availability slots.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view a specific availability slot.
     */
    public function view(Authenticatable $user, InterviewerAvailability $availability): Response
    {
        if ($this->isPlatformAdmin($user)) {
            return Response::deny('Platform administrators manage availability via administrative controls.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot view internal interviewer availability.');
        }

        if ((int) $availability->interviewer_id === (int) $user->getAuthIdentifier() || $this->hasRecruitmentPrivilege($user)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view this availability slot.');
    }

    /**
     * Determine whether the user can create availability slots.
     */
    public function create(Authenticatable $user): Response
    {
        if ($this->isPlatformAdmin($user)) {
            return Response::deny('Platform administrators cannot directly create operational interviewer availability.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot create interviewer availability slots.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the availability slot.
     */
    public function update(Authenticatable $user, InterviewerAvailability $availability): Response
    {
        if ($this->isPlatformAdmin($user)) {
            return Response::deny('Platform administrators cannot directly modify operational interviewer availability.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot update interviewer availability slots.');
        }

        if ((int) $availability->interviewer_id === (int) $user->getAuthIdentifier() || $this->hasRecruitmentPrivilege($user)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to modify this availability slot.');
    }

    /**
     * Determine whether the user can delete the availability slot.
     */
    public function delete(Authenticatable $user, InterviewerAvailability $availability): Response
    {
        if ($this->isPlatformAdmin($user)) {
            return Response::deny('Platform administrators cannot directly delete operational interviewer availability.');
        }

        if ($this->isCandidate($user)) {
            return Response::deny('Candidates cannot delete interviewer availability slots.');
        }

        if ((int) $availability->interviewer_id === (int) $user->getAuthIdentifier() || $this->hasRecruitmentPrivilege($user)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to delete this availability slot.');
    }

    /**
     * Helper to verify company recruitment privilege.
     */
    public function hasRecruitmentPrivilege(Authenticatable $user): bool
    {
        if ($this->isPlatformAdmin($user)) {
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
     * Helper to determine whether the user is a Candidate.
     */
    protected function isCandidate(Authenticatable $user): bool
    {
        if ($this->isPlatformAdmin($user)) {
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

    protected function isPlatformAdmin(Authenticatable $user): bool
    {
        return isset($user->account_type) && $user->account_type === 'admin';
    }
}
