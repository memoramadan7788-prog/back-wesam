<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterviewEvaluation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * The table associated with the model (Recruitment Platform ERD Page 4).
     */
    protected $table = 'interview_evaluations';

    /**
     * The attributes that are mass assignable.
     * Note: Evaluator is strictly evaluator_id (never client-supplied as untrusted).
     *
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'evaluator_id',
        'numeric_score',
        'internal_notes',
        'candidate_feedback',
        'feedback_shared_at',
        'UID',
        'LUID',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numeric_score' => 'float',
            'feedback_shared_at' => 'datetime',
        ];
    }

    /**
     * The associated interview.
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class, 'interview_id');
    }

    /**
     * The evaluator user.
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
