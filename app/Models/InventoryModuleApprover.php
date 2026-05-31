<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryModuleApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_module_config_id',
        'user_id',
        'approval_order',
    ];

    public function config()
    {
        return $this->belongsTo(InventoryModuleConfig::class, 'inventory_module_config_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
