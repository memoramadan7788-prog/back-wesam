<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Interview extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * The table associated with the model (Recruitment Platform ERD Page 4).
     */
    protected $table = 'interviews';

    /**
     * The attributes that are mass assignable.
     * Note: application_id is the confirmed foreign key to applications.id.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'round_no',
        'interview_type',
        'status',
        'meeting_link',
        'location',
        'instructions',
        'created_by',
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
            'round_no' => 'integer',
        ];
    }

    /**
     * Associated interview bookings with interviewers and availability slots (ERD Page 4).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(InterviewBooking::class, 'interview_id');
    }

    public function interviewBookings(): HasMany
    {
        return $this->bookings();
    }

    /**
     * Associated interview evaluations (ERD Page 4).
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class, 'interview_id');
    }

    public function interviewEvaluations(): HasMany
    {
        return $this->evaluations();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * User who scheduled/created the interview.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for candidate-specific interviews.
     * Traverses the confirmed ERD ownership chain via Query Builder subquery without requiring
     * external Candidate or Application model classes:
     * interviews.application_id -> applications.id -> applications.candidate_id -> candidate_profiles.id -> candidate_profiles.user_id
     */
    public function scopeForCandidate(Builder $query, int|string $userId): Builder
    {
        return $query->whereIn('application_id', function ($sub) use ($userId) {
            $sub->select('applications.id')
                ->from('applications')
                ->join('candidate_profiles', 'candidate_profiles.id', '=', 'applications.candidate_id')
                ->where('candidate_profiles.user_id', $userId);
        });
    }

    /**
     * Scope for interviewer-assigned interviews via interview_bookings.
     */
    public function scopeForInterviewer(Builder $query, int|string $interviewerId): Builder
    {
        return $query->whereHas('bookings', function (Builder $q) use ($interviewerId) {
            $q->where('interviewer_id', $interviewerId);
        });
    }

    /**
     * Scope for upcoming interviews based on active booking start times.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereHas('bookings', function (Builder $q) {
                $q->where('start_at', '>=', Carbon::now())
                  ->where('booking_status', '!=', 'cancelled');
            });
    }

    /**
     * Scope for past interviews.
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->whereHas('bookings', function (Builder $q) {
            $q->where('start_at', '<', Carbon::now());
        });
    }

    /**
     * Scope to filter by status.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (!$status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Check if the interview can be rescheduled.
     */
    public function canBeRescheduled(): bool
    {
        return strtolower((string) $this->status) !== 'completed' && strtolower((string) $this->status) !== 'cancelled';
    }

    /**
     * Check if the interview can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return strtolower((string) $this->status) !== 'completed' && strtolower((string) $this->status) !== 'cancelled';
    }

    /**
     * Check if evaluation can be submitted.
     */
    public function canBeEvaluated(): bool
    {
        return strtolower((string) $this->status) !== 'cancelled';
    }
}
