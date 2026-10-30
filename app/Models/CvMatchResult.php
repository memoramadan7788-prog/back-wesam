<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CvMatchResult extends Model
{
    use SoftDeletes, Auditable;

    public const PENDING = 'pending';
    public const OK = 'ok';
    public const FAILED = 'failed';

    protected $table = 'cv_match_results';
    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'matched_skills' => 'array',
        'missing_skills' => 'array',
        'details' => 'array',
        'finished_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function cv(): BelongsTo
    {
        return $this->belongsTo(CandidateCv::class, 'cv_id');
    }

    public function aiReviewDecisions(): HasMany
    {
        return $this->hasMany(AiReviewDecision::class, 'match_result_id');
    }

    public function latestDecision(): HasOne
    {
        return $this->hasOne(AiReviewDecision::class, 'match_result_id')->latestOfMany();
    }
}
