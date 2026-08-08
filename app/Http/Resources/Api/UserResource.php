<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'status' => $this->status,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'default_store_id' => $this->default_store_id,
            'profile_photo_url' => $this->profile_photo_url,
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business?->id,
                'name' => $this->business?->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'default_store' => $this->whenLoaded('defaultStore', fn () => [
                'id' => $this->defaultStore?->id,
                'name' => $this->defaultStore?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
