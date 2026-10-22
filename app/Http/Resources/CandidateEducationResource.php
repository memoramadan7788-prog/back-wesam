<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CandidateEducation
 */
class CandidateEducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'institution'     => $this->institution,
            'degree'          => $this->degree,
            'field_of_study'  => $this->field_of_study,
            'graduation_year' => $this->graduation_year,
            'place'           => $this->place,
        ];
    }
}

