<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateOnboarding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'completion_percent' => 'integer',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
}
