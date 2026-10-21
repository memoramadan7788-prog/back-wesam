<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCategory extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'job_categories';
    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'parent_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'category_id');
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class, 'category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(JobCategory::class, 'parent_id');
    }
}
