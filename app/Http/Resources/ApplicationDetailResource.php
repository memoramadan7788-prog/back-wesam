<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Application
 */
class ApplicationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'job_id'       => $this->job_id,
            'job'          => [
                'id'       => $this->job?->id,
                'title'    => $this->job?->title,
                'company'  => $this->job?->company?->name,
                'location' => $this->job?->location,
                'hiring_model' => $this->job?->hiring_model,
                'requisition_code' => $this->job?->requisition_code,
                'salary' => [
                    'min' => $this->job?->salary_min,
                    'max' => $this->job?->salary_max,
                    'currency' => $this->job?->currency,
                    'period' => $this->job?->salary_period,
                ],
            ],
            'candidate_id' => $this->candidate_id,
            'candidate'    => [
                'id'               => $this->candidateProfile?->id,
                'name'             => $this->candidateProfile?->user?->name,
                'email'            => $this->candidateProfile?->user?->email,
                'phone'            => $this->candidateProfile?->phone,
                'headline'         => $this->candidateProfile?->headline,
                'years_experience' => $this->candidateProfile?->years_experience,
            ],
            'cv'           => $this->candidateCv ? [
                'id'                => $this->candidateCv->id,
                'version'           => $this->candidateCv->version,
                'original_filename' => $this->candidateCv->original_filename,
            ] : null,
            'cover_note'   => $this->cover_note,
            'answers'      => $this->answers ?? [],
            'expected_salary' => $this->expected_salary,
            'portfolio_url' => $this->portfolio_url,
            'keep_in_talent_pool' => (bool) $this->keep_in_talent_pool,
            'status'       => $this->status,
            'attempt_no'   => $this->attempt_no,
            'history'      => $this->whenLoaded('applicationStatusHistories', fn () => $this->applicationStatusHistories->map(fn ($h) => [
                'from'       => $h->from_status,
                'to'         => $h->to_status,
                'changed_by' => $h->changer?->name,
                'reason'     => $h->reason,
                'comment'    => $h->reason,
                'changed_at' => $h->created_at?->toIso8601String(),
            ])),
            'timeline' => $this->whenLoaded('applicationStatusHistories', fn () => $this->applicationStatusHistories->map(fn ($h) => [
                'type' => 'status_change',
                'status' => $h->to_status,
                'description' => $h->reason,
                'occurred_at' => ($h->changed_at ?? $h->created_at)?->toIso8601String(),
            ])->values()),
            'next_interview' => $this->whenLoaded('interviews', function () {
                $pair = $this->interviews
                    ->filter(fn ($item) => $item->status !== 'cancelled')
                    ->flatMap(fn ($item) => $item->bookings->map(fn ($booking) => [$item, $booking]))
                    ->filter(fn ($item) => $item[1]->booking_status !== 'cancelled' && $item[1]->start_at?->isFuture())
                    ->sortBy(fn ($item) => $item[1]->start_at)
                    ->first();

                if (! $pair) {
                    return null;
                }

                [$interview, $booking] = $pair;
                return [
                    'id' => $interview->id,
                    'round_no' => $interview->round_no,
                    'status' => $interview->status,
                    'interview_type' => $interview->interview_type,
                    'start_at' => $booking->start_at?->toIso8601String(),
                    'end_at' => $booking->end_at?->toIso8601String(),
                    'location' => $interview->location,
                    'meeting_link' => $interview->meeting_link,
                ];
            }),
            'hiring_contacts' => $this->when(
                $this->job?->relationLoaded('company') && $this->job->company?->relationLoaded('companyEmployees'),
                function () {
                    $contacts = collect();
                    if ($this->job->company?->owner) {
                        $contacts->push([
                            'name' => $this->job->company->owner->name,
                            'email' => $this->job->company->owner->email,
                            'role' => 'owner',
                        ]);
                    }
                    foreach ($this->job->company->companyEmployees->where('status', 'approved') as $employee) {
                        if ($employee->user) {
                            $contacts->push([
                                'name' => $employee->user->name,
                                'email' => $employee->user->email,
                                'role' => $employee->role?->name,
                            ]);
                        }
                    }
                    return $contacts->unique('email')->values();
                }
            ),
            'submitted_documents' => [
                'cv' => $this->candidateCv ? [
                    'id' => $this->candidateCv->id,
                    'version' => $this->candidateCv->version,
                    'original_filename' => $this->candidateCv->original_filename,
                ] : null,
                'portfolio_url' => $this->portfolio_url,
            ],
            'applied_at'   => $this->applied_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
        ];
    }
}

