<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detailed single job representation including full description and department context.
 *
 * @mixin \App\Models\Job
 */
class JobDetailResource extends JobResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description'    => $this->description,
            'department'     => $this->department,
            'openings_count' => $this->openings_count,
            'is_accepting_applications' => true,
        ]);
    }
}
