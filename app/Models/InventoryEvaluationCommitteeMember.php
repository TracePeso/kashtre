<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryEvaluationCommitteeMember extends Model
{
    public const ROLE_CHAIR = 'chair';

    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'inventory_module_config_id',
        'user_id',
        'role',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function moduleConfig(): BelongsTo
    {
        return $this->belongsTo(InventoryModuleConfig::class, 'inventory_module_config_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleLabel(): string
    {
        return $this->role === self::ROLE_CHAIR ? 'Chair' : 'Member';
    }
}
