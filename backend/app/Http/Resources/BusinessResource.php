<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'registration_number' => $this->registration_number,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'logo_path' => $this->logo_path,
            'status' => $this->status,
            'allow_negative_stock' => (bool) $this->allow_negative_stock,
            'multi_branch_enabled' => (bool) $this->multi_branch_enabled,
            'settings' => $this->settings,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'is_owner' => $this->whenPivotLoaded('business_user', fn () => (bool) $this->pivot->is_owner),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
