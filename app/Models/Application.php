<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'applications';
    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
        'expected_salary' => 'decimal:2',
        'keep_in_talent_pool' => 'boolean',
        'consent_at' => 'datetime',
        'applied_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->candidate();
    }

    public function cv(): BelongsTo
    {
        return $this->belongsTo(CandidateCv::class, 'cv_id');
    }

    public function candidateCv(): BelongsTo
    {
        return $this->cv();
    }

    public function applicationStatusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'application_id');
    }

    public function applicationStatusHistories(): HasMany
    {
        return $this->applicationStatusHistory();
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'application_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'application_id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'application_id');
    }

    public function cvMatchResults(): HasMany
    {
        return $this->hasMany(CvMatchResult::class, 'application_id');
    }

    public function screeningAnswers(): HasMany
    {
        return $this->hasMany(JobScreeningQuestion::class, 'job_id', 'job_id');
    }
}
