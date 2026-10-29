<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePreference extends Model
{
    protected $guarded = [];

    protected $casts = [
        'desired_titles' => 'array',
        'min_salary' => 'decimal:2',
        'open_to_relocation' => 'boolean',
        'include_agency' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
}
