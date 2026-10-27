<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Admin */
class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_status_id' => $this->account_status_id,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->whenLoaded('roles', fn () => $this->getAllPermissions()),
        ];
    }
}
