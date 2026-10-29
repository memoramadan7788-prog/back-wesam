<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocument extends Model
{
    use SoftDeletes;

    public const TYPES = ['commercial_register', 'tax_card', 'license', 'other'];

    protected $table = 'company_documents';
    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function uploaderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function uploaderAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }
}
