<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewerAvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $start = $this->start_at ? Carbon::parse($this->start_at) : null;
        $end = $this->end_at ? Carbon::parse($this->end_at) : null;
        $duration = ($start && $end) ? $start->diffInMinutes($end) : null;

        return [
            'id' => $this->id,
            'interviewer_id' => $this->interviewer_id,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'duration_minutes' => $duration,
            'availability_status' => $this->availability_status,
            'interviewer' => $this->whenLoaded('interviewer', function () {
                return [
                    'id' => $this->interviewer->id,
                    'name' => $this->interviewer->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
