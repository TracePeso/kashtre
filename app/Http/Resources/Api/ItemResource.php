<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Item */
class ItemResource extends JsonResource
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
            'generic_name' => $this->generic_name,
            'strength' => $this->strength,
            'category' => $this->category,
            'code' => $this->code,
            'type' => $this->type,
            'importance_category' => $this->importance_category,
            'description' => $this->description,
            'default_price' => $this->default_price,
            'purchase_price' => $this->purchase_price,
            'vat_rate' => $this->vat_rate,
            'business_id' => $this->business_id,
            'group_id' => $this->group_id,
            'subgroup_id' => $this->subgroup_id,
            'department_id' => $this->department_id,
            'uom_id' => $this->uom_id,
            'order_unit_id' => $this->order_unit_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
