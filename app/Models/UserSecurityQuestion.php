<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSecurityQuestion extends Model
{
    protected $fillable = [
        'user_id',
        'question_key',
        'answer_hash',
        'sort_order',
    ];

    protected $hidden = [
        'answer_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
