<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSavedSearch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'filters' => 'array',
        'weekly_alerts' => 'boolean',
        'is_paused' => 'boolean',
        'last_alerted_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
}
