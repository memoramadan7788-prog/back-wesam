<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleCategory extends Model
{
    protected $table = 'role_categories';
    protected $guarded = [];

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'role_category_id');
    }
}
