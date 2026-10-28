<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, SoftDeletes, Auditable;


    protected $table = 'companies';
    protected $guarded = [];

    protected $attributes = [
        'account_status_id' => AccountStatus::ACTIVE,
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

        public function accountStatus(): BelongsTo
    {
        return $this->belongsTo(AccountStatus::class, 'account_status_id');
    }

public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewerAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'company_id');
    }

    public function companySocialLinks(): HasMany
    {
        return $this->hasMany(CompanySocialLink::class, 'company_id');
    }

    public function companyEmployees(): HasMany
    {
        return $this->hasMany(CompanyEmployee::class, 'company_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'company_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'company_id');
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class, 'company_id');
    }
}
