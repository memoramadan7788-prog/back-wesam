<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'action'         => $this->action,
            'auditable_type' => class_basename($this->auditable_type),
            'auditable_id'   => $this->auditable_id,
            'user'           => $this->whenLoaded('user', fn () => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'admin'          => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id'    => $this->admin->id,
                'name'  => $this->admin->name,
                'email' => $this->admin->email,
            ] : null),
            'old_values'     => $this->old_values,
            'new_values'     => $this->new_values,
            'reason'         => $this->reason,
            'ip_address'     => $this->ip_address,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}

