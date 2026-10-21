<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interview extends Model
{
    use SoftDeletes, Auditable;


    protected $table = 'interviews';
    protected $guarded = [];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interviewBookings(): HasMany
    {
        return $this->hasMany(InterviewBooking::class, 'interview_id');
    }

    public function interviewEvaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class, 'interview_id');
    }
}
