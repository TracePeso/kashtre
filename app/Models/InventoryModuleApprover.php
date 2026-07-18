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
        'role',
        'approval_order',
    ];

    public const ROLE_APPROVER = 'approver';

    public const ROLE_TECHNICAL_SUPERVISOR = 'technical_supervisor';

    public function isTechnicalSupervisor(): bool
    {
        return $this->role === self::ROLE_TECHNICAL_SUPERVISOR;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_TECHNICAL_SUPERVISOR => 'Technical supervisor',
            default => 'Approver '.$this->approval_order,
        };
    }

    public function config()
    {
        return $this->belongsTo(InventoryModuleConfig::class, 'inventory_module_config_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
