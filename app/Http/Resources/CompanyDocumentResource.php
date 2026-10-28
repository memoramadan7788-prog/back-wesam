<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CompanyDocument
 */
class CompanyDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'company_id'        => $this->company_id,
            'type'              => $this->type,
            'title'             => $this->title,
            'original_filename' => $this->original_filename,
            'mime_type'         => $this->mime_type,
            'size_bytes'        => (int) $this->size_bytes,
            'uploaded_by'       => $this->uploaded_by_admin_id ? 'admin' : 'company',
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
