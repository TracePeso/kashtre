<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOrderCommitteeMember extends Model
{
    public const ROLE_CHAIR = 'chair';

    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'inventory_order_id',
        'user_id',
        'role',
        'sort_order',
        'appointed_by_user_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class, 'inventory_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointed_by_user_id');
    }

    public function roleLabel(): string
    {
        return $this->role === self::ROLE_CHAIR ? 'Chair' : 'Member';
    }
}
