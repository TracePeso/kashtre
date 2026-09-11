<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingServicePointConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'main_room_id',
        'inventory_store_id',
        'hardware_ae_title',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function resolveRoom(): ?Room
    {
        return Room::find($this->main_room_id);
    }

    public function resolveInventoryStore(): ?Store
    {
        return $this->inventory_store_id ? Store::find($this->inventory_store_id) : null;
    }
}
