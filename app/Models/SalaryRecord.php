<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryRecord extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'salary_records';
    protected $guarded = [];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'category_id');
    }

    public function salaryRecommendations(): HasMany
    {
        return $this->hasMany(SalaryRecommendation::class, 'salary_record_id');
    }
}
