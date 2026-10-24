<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Role
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'scope'       => $this->scope,
            'description' => $this->description,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('key')),
        ];
    }
}

