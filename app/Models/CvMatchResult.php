<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CvMatchResult extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'cv_match_results';
    protected $guarded = [];

    protected $casts = [
        'matched_skills' => 'array',
        'missing_skills' => 'array',
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
}
