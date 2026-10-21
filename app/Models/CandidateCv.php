<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateCv extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'candidate_cvs';
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'cv_id');
    }

    public function cvMatchResults(): HasMany
    {
        return $this->hasMany(CvMatchResult::class, 'cv_id');
    }
}
