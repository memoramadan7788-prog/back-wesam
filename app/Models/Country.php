<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    public $timestamps = true;

    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_id');
    }
}
