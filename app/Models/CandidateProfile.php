<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateProfile extends Model
{
    protected $table = 'candidate_profiles';

    public $timestamps = false;

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

    public function candidateEducation(): HasMany
    {
        return $this->hasMany(CandidateEducation::class, 'candidate_id');
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

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills', 'candidate_id', 'skill_id')->withPivot('level');
    }
}
