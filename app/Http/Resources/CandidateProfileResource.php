<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CandidateProfile
 */
class CandidateProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'name'              => $this->user?->name,
            'email'             => $this->user?->email,
            'headline'          => $this->headline,
            'bio'               => $this->bio,
            'phone'             => $this->phone,
            'location'          => $this->location,
            'photo_path'        => $this->photo_path,
            'years_experience'  => $this->years_experience,
            'has_no_experience' => (bool) $this->has_no_experience,
            'experiences'       => CandidateExperienceResource::collection($this->whenLoaded('candidateExperiences')),
            'educations'        => CandidateEducationResource::collection($this->whenLoaded('candidateEducations')),
            'cvs'               => CandidateCvResource::collection($this->whenLoaded('candidateCvs')),
        ];
    }
}

