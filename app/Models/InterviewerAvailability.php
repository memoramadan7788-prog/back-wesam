<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterviewerAvailability extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * The table associated with the model (Recruitment Platform ERD Page 4).
     */
    protected $table = 'interviewer_availability';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'interviewer_id',
        'start_at',
        'end_at',
        'availability_status',
        'UID',
        'LUID',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * The interviewer user.
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    /**
     * Bookings attached to this availability slot.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(InterviewBooking::class, 'availability_id');
    }

    public function interviewBookings(): HasMany
    {
        return $this->bookings();
    }
}
