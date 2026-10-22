<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, Auditable;


    protected $table = 'users';
    protected $guarded = [];

    protected $attributes = [
        'account_status_id' => AccountStatus::ACTIVE,
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

        public function accountStatus(): BelongsTo
    {
        return $this->belongsTo(AccountStatus::class, 'account_status_id');
    }

    public function isActive(): bool
    {
        return $this->account_status_id === AccountStatus::ACTIVE;
    }

    public function isDeactivated(): bool
    {
        return $this->account_status_id === AccountStatus::DEACTIVATED;
    }

    public function isBlocked(): bool
    {
        return $this->account_status_id === AccountStatus::BLOCKED;
    }

    public function isDeleted(): bool
    {
        return $this->account_status_id === AccountStatus::DELETED;
    }

public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class, 'user_id');
    }

    public function companyEmployee(): HasOne
    {
        return $this->hasOne(CompanyEmployee::class, 'user_id');
    }

    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('company_id', 'assigned_by', 'assigned_at');
    }

    public function receivedNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    public function chatbotSessions(): HasMany
    {
        return $this->hasMany(ChatbotSession::class, 'user_id');
    }

    public function calendarIntegrations(): HasMany
    {
        return $this->hasMany(CalendarIntegration::class, 'user_id');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(InterviewerAvailability::class, 'interviewer_id');
    }
}
