<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'countries';
    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_id');
    }
}
