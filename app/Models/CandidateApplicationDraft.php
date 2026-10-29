<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CandidateApplicationDraft extends Model
{
    protected $table = 'candidate_application_drafts';
    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
        'expected_salary' => 'decimal:2',
        'keep_in_talent_pool' => 'boolean',
        'consent' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function cv(): BelongsTo
    {
        return $this->belongsTo(CandidateCv::class, 'cv_id');
    }
}
