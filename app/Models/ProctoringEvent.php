<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProctoringEvent extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'proctoring_events';
    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function proctoringReviews(): HasMany
    {
        return $this->hasMany(ProctoringReview::class, 'event_id');
    }
}
