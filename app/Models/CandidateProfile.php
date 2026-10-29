<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CandidateProfile extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'candidate_profiles';
    protected $guarded = [];

    protected $casts = [
        'has_no_experience' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function candidateExperience(): HasMany
    {
        return $this->hasMany(CandidateExperience::class, 'candidate_id');
    }

    public function candidateExperiences(): HasMany
    {
        return $this->candidateExperience();
    }

    public function candidateEducation(): HasMany
    {
        return $this->hasMany(CandidateEducation::class, 'candidate_id');
    }

    public function candidateEducations(): HasMany
    {
        return $this->candidateEducation();
    }

    public function candidateCourses(): HasMany
    {
        return $this->hasMany(CandidateCourse::class, 'candidate_id');
    }

    public function candidateSocialLinks(): HasMany
    {
        return $this->hasMany(CandidateSocialLink::class, 'candidate_id');
    }

    public function candidateCvs(): HasMany
    {
        return $this->hasMany(CandidateCv::class, 'candidate_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'candidate_id');
    }

    public function applicationDrafts(): HasMany
    {
        return $this->hasMany(CandidateApplicationDraft::class, 'candidate_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills', 'candidate_id', 'skill_id')->withPivot('level');
    }

    public function savedJobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'candidate_saved_jobs', 'candidate_id', 'job_id')
            ->withTimestamps();
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(CandidateSavedSearch::class, 'candidate_id');
    }

    public function hiddenJobs(): HasMany
    {
        return $this->hasMany(CandidateHiddenJob::class, 'candidate_id');
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(CandidateOnboarding::class, 'candidate_id');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(CandidatePreference::class, 'candidate_id');
    }

    public function notificationSettings(): HasOne
    {
        return $this->hasOne(CandidateNotificationSetting::class, 'candidate_id');
    }

    public function privacySettings(): HasOne
    {
        return $this->hasOne(CandidatePrivacySetting::class, 'candidate_id');
    }
}
