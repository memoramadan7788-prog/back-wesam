<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'exam_attempts';
    protected $guarded = [];

    protected $casts = [
        'camera_consent_at' => 'datetime',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }

    public function gradingResults(): HasMany
    {
        return $this->hasMany(GradingResult::class, 'attempt_id');
    }

    public function proctoringEvents(): HasMany
    {
        return $this->hasMany(ProctoringEvent::class, 'attempt_id');
    }
}
