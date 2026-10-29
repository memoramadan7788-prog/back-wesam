<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Safe candidate profile representation for the candidate's employer preview.
 * Contact details and private account data are deliberately omitted.
 *
 * @mixin \App\Models\CandidateProfile
 */
class CandidateProfilePreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->user?->name,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'location' => $this->location,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'years_experience' => $this->years_experience,
            'has_no_experience' => (bool) $this->has_no_experience,
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'type' => $skill->type,
                'level' => $skill->pivot?->level,
            ])->values()),
            'experiences' => CandidateExperienceResource::collection($this->whenLoaded('candidateExperiences')),
            'educations' => CandidateEducationResource::collection($this->whenLoaded('candidateEducations')),
            'active_cv' => $this->whenLoaded('candidateCvs', function () {
                $cv = $this->candidateCvs->firstWhere('is_active', true);
                return $cv ? new CandidateCvResource($cv) : null;
            }),
        ];
    }
}
