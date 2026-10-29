<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateNotificationSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weekly_digest' => 'boolean',
        'status_updates' => 'boolean',
        'interview_reminders' => 'boolean',
        'messages' => 'boolean',
        'marketing' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
}
