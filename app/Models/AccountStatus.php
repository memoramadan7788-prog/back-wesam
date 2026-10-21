<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountStatus extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'account_statuses';

    protected $guarded = [];

    public const ACTIVE = 1;
    public const DEACTIVATED = 2;
    public const BLOCKED = 3;
    public const DELETED = 4;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_status_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'account_status_id');
    }
}

