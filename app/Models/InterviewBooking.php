<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterviewBooking extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * The table associated with the model (Recruitment Platform ERD Page 4).
     */
    protected $table = 'interview_bookings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'interviewer_id',
        'availability_id',
        'start_at',
        'end_at',
        'booking_status',
        'cancelled_by',
        'cancelled_at',
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
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Associated interview.
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class, 'interview_id');
    }

    /**
     * Assigned interviewer user.
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    /**
     * Associated interviewer availability slot.
     */
    public function availability(): BelongsTo
    {
        return $this->belongsTo(InterviewerAvailability::class, 'availability_id');
    }

    /**
     * User who cancelled the booking (if cancelled).
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->cancelledBy();
    }
}
