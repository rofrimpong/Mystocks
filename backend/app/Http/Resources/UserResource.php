<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_platform_admin' => (bool) $this->is_platform_admin,
            'is_active' => (bool) $this->is_active,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatar_path ? asset('storage/'.$this->avatar_path) : null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
