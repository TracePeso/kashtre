<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pillar 9.1: Contrast Chemical Asset Monitoring Lifecycle. Same
 * status-machine idiom used throughout this module (ImagingStudy's 9-state
 * machine, ImagingReport's 4-state lifecycle, RecoveryRecord's discharge
 * lock) — constants + guarded mark*() transition methods.
 */
class ContrastVial extends Model
{
    use HasFactory;

    protected $table = 'imaging_contrast_vials';

    const STATUS_UNOPENED = 'UNOPENED';
    const STATUS_ONBOARD = 'ONBOARD';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_EXHAUSTED = 'EXHAUSTED';

    const STATUSES = [
        self::STATUS_UNOPENED,
        self::STATUS_ONBOARD,
        self::STATUS_EXPIRED,
        self::STATUS_EXHAUSTED,
    ];

    protected $fillable = [
        'business_id',
        'item_id',
        'agent_name',
        'lot_number',
        'total_volume_ml',
        'remaining_volume_ml',
        'stability_hours',
        'status',
        'opened_at',
    ];

    protected $casts = [
        'total_volume_ml' => 'decimal:2',
        'remaining_volume_ml' => 'decimal:2',
        'stability_hours' => 'integer',
        'opened_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (ContrastVial $vial) {
            if (empty($vial->status)) {
                $vial->status = self::STATUS_UNOPENED;
            }

            if ($vial->remaining_volume_ml === null) {
                $vial->remaining_volume_ml = $vial->total_volume_ml;
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * item_id is a plain indexed column, not a FK (same cross-domain-
     * decoupling rule as everywhere else in this module) — a lookup, not
     * a real Eloquent relation.
     */
    public function resolveItem(): ?Item
    {
        return $this->item_id ? Item::find($this->item_id) : null;
    }

    public function isStatus(string $status): bool
    {
        return $this->status === $status;
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * What the Contrast Administration picker offers — not yet expired or
     * exhausted. isPastStabilityWindow() is checked separately at the
     * moment of use (deduct()), since a stale query result shouldn't be
     * trusted for that decision.
     */
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_UNOPENED, self::STATUS_ONBOARD]);
    }

    /**
     * Lazy computation against a stored timestamp — same idiom as this
     * codebase's PackageTracking::isExpired()/PackageUsage::isExpired()
     * (valid_until < now()) — rather than a scheduled command. There's no
     * need for a background job just to flag a vial nobody has tried to
     * draw from yet; the check that matters happens at the point of use.
     */
    public function isPastStabilityWindow(): bool
    {
        if (! $this->stability_hours || ! $this->opened_at) {
            return false;
        }

        return $this->opened_at->addHours($this->stability_hours)->isPast();
    }

    public function markOnboard(): void
    {
        if (! $this->isStatus(self::STATUS_UNOPENED)) {
            throw new \RuntimeException("Contrast vial #{$this->id} is not unopened.");
        }

        $this->update([
            'status' => self::STATUS_ONBOARD,
            'opened_at' => now(),
        ]);
    }

    /**
     * Deducts $ml from the vial at the moment of use, enforcing both the
     * onboard state and the stability window right here rather than
     * trusting whatever scopeAvailable() returned earlier in the request.
     */
    public function deduct(float $ml): void
    {
        if (! $this->isStatus(self::STATUS_ONBOARD)) {
            throw new \RuntimeException("Contrast vial #{$this->id} is not onboard.");
        }

        if ($this->isPastStabilityWindow()) {
            $this->update(['status' => self::STATUS_EXPIRED]);

            throw new \RuntimeException("Contrast vial #{$this->id} is past its stability window.");
        }

        $remaining = (float) $this->remaining_volume_ml - $ml;

        $this->update([
            'remaining_volume_ml' => max($remaining, 0),
            'status' => $remaining <= 0 ? self::STATUS_EXHAUSTED : self::STATUS_ONBOARD,
        ]);
    }
}
