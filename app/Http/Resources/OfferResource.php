<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Offer
 */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'application_id' => $this->application_id,
            'job_title'      => $this->application?->job?->title,
            'company_name'   => $this->application?->job?->company?->name,
            'salary_amount'  => (float) $this->salary_amount,
            'currency'       => $this->currency,
            'salary_period'  => $this->salary_period,
            'start_date'     => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'status'         => $this->status,
            'sent_at'        => $this->sent_at?->toIso8601String(),
            'responded_at'   => $this->responded_at?->toIso8601String(),
            'expires_at'     => $this->expires_at?->toIso8601String(),
        ];
    }
}

