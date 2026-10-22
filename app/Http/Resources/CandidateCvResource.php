<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CandidateCv
 */
class CandidateCvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'version'           => $this->version,
            'original_filename' => $this->original_filename,
            'mime_type'         => $this->mime_type,
            'size_bytes'        => $this->size_bytes,
            'is_active'         => (bool) $this->is_active,
            'uploaded_at'       => $this->uploaded_at?->toIso8601String(),
        ];
    }
}

