<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePrivacySetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'allow_agency_recruiters' => 'boolean',
        'show_in_public_search' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
}
