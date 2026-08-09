<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingModuleConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'is_active',
        'description',
        'peer_review_rate',
        'peer_review_eligible_modalities',
        'peer_review_reviewer_pool_user_ids',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'peer_review_rate' => 'integer',
        'peer_review_eligible_modalities' => 'array',
        'peer_review_reviewer_pool_user_ids' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    const DEFAULT_PEER_REVIEW_RATE = 4;

    /**
     * Effective peer-review percentage for a business — falls back to the
     * spec's "4% baseline" when no config row exists yet.
     */
    public static function effectivePeerReviewRate(int $businessId): int
    {
        return static::forBusiness($businessId)->value('peer_review_rate') ?? self::DEFAULT_PEER_REVIEW_RATE;
    }

    /**
     * Empty/unset list = every modality is eligible (today's behavior).
     */
    public static function isModalityEligibleForPeerReview(int $businessId, string $modalityType): bool
    {
        $eligible = static::forBusiness($businessId)->value('peer_review_eligible_modalities');

        if (empty($eligible)) {
            return true;
        }

        return in_array($modalityType, is_string($eligible) ? json_decode($eligible, true) : $eligible, true);
    }

    /**
     * Empty/unset pool = any user holding the review permission is eligible
     * (today's behavior) — this only narrows the pool further.
     *
     * The pool is populated via a Filament multi-select, which submits
     * (and therefore stores) user IDs as strings — a strict in_array()
     * against the int $userId from Auth::id() would silently reject every
     * real user, so both sides are normalized to int before comparing.
     */
    public static function isEligibleReviewer(int $businessId, int $userId): bool
    {
        $pool = static::forBusiness($businessId)->value('peer_review_reviewer_pool_user_ids');

        if (empty($pool)) {
            return true;
        }

        $pool = is_string($pool) ? json_decode($pool, true) : $pool;

        return in_array($userId, array_map('intval', $pool), true);
    }
}
