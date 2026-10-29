<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'jobs';
    protected $guarded = [];

    protected $casts = [
        'deadline' => 'datetime',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobInterviewRounds(): HasMany
    {
        return $this->hasMany(JobInterviewRound::class, 'job_id');
    }

    public function jobNominations(): HasMany
    {
        return $this->hasMany(JobNomination::class, 'job_id');
    }

    public function jobExams(): HasMany
    {
        return $this->hasMany(JobExam::class, 'job_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'job_id');
    }

    public function screeningQuestions(): HasMany
    {
        return $this->hasMany(JobScreeningQuestion::class, 'job_id')->orderBy('sort_order');
    }

    public function applicationDrafts(): HasMany
    {
        return $this->hasMany(CandidateApplicationDraft::class, 'job_id');
    }

    public function salaryRecommendations(): HasMany
    {
        return $this->hasMany(SalaryRecommendation::class, 'job_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_skills', 'job_id', 'skill_id')->withPivot('is_required');
    }

    public function savedByCandidates(): BelongsToMany
    {
        return $this->belongsToMany(CandidateProfile::class, 'candidate_saved_jobs', 'job_id', 'candidate_id')
            ->withTimestamps();
    }

    public const SORTABLE = [
        'published_at' => 'jobs.published_at',
        'title'        => 'jobs.title',
        'salary_min'   => 'jobs.salary_min',
        'deadline'     => 'jobs.deadline',
    ];

    public const PUBLIC_COLUMNS = [
        'jobs.id', 'jobs.company_id', 'jobs.category_id', 'jobs.title',
        'jobs.description', 'jobs.location', 'jobs.work_mode',
        'jobs.employment_type', 'jobs.salary_min', 'jobs.salary_max',
        'jobs.currency', 'jobs.salary_period', 'jobs.experience_years',
        'jobs.department', 'jobs.openings_count', 'jobs.deadline',
        'jobs.published_at', 'jobs.updated_at', 'jobs.requisition_code',
        'jobs.hiring_model',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('jobs.status', 'published')
            ->where('jobs.visibility', 'public')
            ->whereNull('jobs.closed_at')
            ->where(function (Builder $q) {
                $q->whereNull('jobs.deadline')
                  ->orWhere('jobs.deadline', '>', now());
            })
            ->whereHas('company', fn (Builder $c) => $c->where('status', 'approved'));
    }

    public function scopeAvailableInternal(Builder $query, int $companyId, ?int $userId = null): Builder
    {
        return $query
            ->where('jobs.company_id', $companyId)
            ->where('jobs.status', 'published')
            ->where('jobs.visibility', 'private')
            ->whereNull('jobs.closed_at')
            ->where(function (Builder $q) {
                $q->whereNull('jobs.deadline')
                    ->orWhere('jobs.deadline', '>', now());
            })
            ->where(function (Builder $q) use ($userId, $companyId) {
                $q->where('jobs.private_apply_mode', 'all_employees')
                    ->orWhereNull('jobs.private_apply_mode');

                if ($userId) {
                    $q->orWhereHas('jobNominations', function (Builder $nomination) use ($userId, $companyId) {
                        $nomination
                            ->whereHas('employee', fn (Builder $employee) => $employee
                                ->where('user_id', $userId)
                                ->where('company_id', $companyId)
                                ->where('status', 'approved'));
                    });
                }
            })
            ->whereHas('company', fn (Builder $c) => $c->where('status', 'approved'));
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, function (Builder $q, string $term) {
                $like = self::likeTerm($term);
                $q->where(function (Builder $w) use ($like) {
                    $w->where('jobs.title', 'like', $like)
                      ->orWhere('jobs.description', 'like', $like)
                      ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like));
                });
            })
            ->when($f['title'] ?? null, fn (Builder $q, string $v) => $q->where('jobs.title', 'like', self::likeTerm($v)))
            ->when($f['company_name'] ?? null, fn (Builder $q, string $v) => $q->whereHas(
                'company', fn (Builder $c) => $c->where('name', 'like', self::likeTerm($v))
            ))
            ->when($f['location'] ?? null, fn (Builder $q, string $v) => $q->where('jobs.location', 'like', self::likeTerm($v)))
            ->when($f['employment_type'] ?? null, fn (Builder $q, array $v) => $q->whereIn('jobs.employment_type', $v))
            ->when($f['work_mode'] ?? null, fn (Builder $q, array $v) => $q->whereIn('jobs.work_mode', $v))
            ->when($f['company_id'] ?? null, fn (Builder $q, $v) => $q->where('jobs.company_id', $v))
            ->when($f['category_id'] ?? null, fn (Builder $q, $v) => $q->where('jobs.category_id', $v))
            ->when($f['skills'] ?? null, fn (Builder $q, array $ids) => $q->whereHas(
                'skills', fn (Builder $s) => $s->whereIn('skills.id', $ids)
            ))
            ->when(isset($f['max_experience']), fn (Builder $q) => $q->where(function (Builder $w) use ($f) {
                $w->whereNull('jobs.experience_years')
                  ->orWhere('jobs.experience_years', '<=', $f['max_experience']);
            }))
            ->when(isset($f['salary_min']), fn (Builder $q) => $q->where('jobs.salary_max', '>=', $f['salary_min']))
            ->when(isset($f['salary_max']), fn (Builder $q) => $q->where('jobs.salary_min', '<=', $f['salary_max']))
            ->when($f['published_from'] ?? null, fn (Builder $q, string $v) => $q->where('jobs.published_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['published_to'] ?? null, fn (Builder $q, string $v) => $q->where('jobs.published_at', '<=', Carbon::parse($v)->endOfDay()));
    }

    private static function likeTerm(string $term): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    }
}
