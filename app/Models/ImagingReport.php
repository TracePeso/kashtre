<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'pacs_study_uid',
        'author_user_id',
        'verified_by_user_id',
        'status',
        'is_critical_finding',
        'critical_finding_code',
        'structured_data_payload',
        'reported_at',
        'verified_at',
    ];

    protected $casts = [
        'is_critical_finding' => 'boolean',
        'structured_data_payload' => 'array',
        'reported_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'DRAFT';
    const STATUS_REPORTED = 'REPORTED';
    const STATUS_VERIFIED = 'VERIFIED';
    const STATUS_AMENDED = 'AMENDED';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REPORTED,
        self::STATUS_VERIFIED,
        self::STATUS_AMENDED,
    ];

    protected static function booted()
    {
        static::creating(function (ImagingReport $report) {
            if (empty($report->status)) {
                $report->status = self::STATUS_DRAFT;
            }
        });
    }

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function versions()
    {
        return $this->hasMany(ReportVersion::class);
    }

    /**
     * critical_finding_code is a plain code string, not a FK — a lookup,
     * not a real Eloquent relation (same rule as elsewhere in this module).
     */
    public function resolveCriticalFindingType(): ?ImagingCriticalFindingType
    {
        if (! $this->critical_finding_code) {
            return null;
        }

        return ImagingCriticalFindingType::where('code', $this->critical_finding_code)
            ->availableToBusiness($this->imagingStudy?->business_id)
            ->first();
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCriticalFindings($query)
    {
        return $query->where('is_critical_finding', true);
    }

    public function isStatus(string $status): bool
    {
        return $this->status === $status;
    }

    protected function requireStatus(string ...$expected): void
    {
        if (! in_array($this->status, $expected, true)) {
            throw new \RuntimeException(
                "Report for study #{$this->imaging_study_id} is '{$this->status}', expected one of [".implode(', ', $expected)."] for this transition."
            );
        }
    }

    public function markReported(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_DRAFT);

        $this->update([
            'status' => self::STATUS_REPORTED,
            'reported_at' => now(),
        ]);

        $this->snapshotVersion($userId ?? $this->author_user_id);
    }

    /**
     * Two independent guards, both enforced here (not just in the UI)
     * since this is the one place every verify path (button, future API,
     * console command) actually goes through:
     *   - Segregation of duties, same rule already enforced for peer
     *     review (PeerReviewCase::markCompleted()) — the radiologist who
     *     wrote the report can't be the one who verifies/signs it off.
     *   - The verifier must be in the business's configured radiologist
     *     pool (ImagingModuleConfig::isEligibleReviewer() — the same pool
     *     peer review already uses), not just any permission-holder.
     */
    public function markVerified(int $verifiedByUserId): void
    {
        $this->requireStatus(self::STATUS_REPORTED, self::STATUS_AMENDED);

        if ($verifiedByUserId === $this->author_user_id) {
            throw new \RuntimeException('The reporting radiologist cannot verify their own report — a colleague must sign it off.');
        }

        if (! ImagingModuleConfig::isEligibleReviewer($this->imagingStudy->business_id, $verifiedByUserId)) {
            throw new \RuntimeException('This user is not in the configured radiologist verification pool for this business.');
        }

        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_by_user_id' => $verifiedByUserId,
            'verified_at' => now(),
        ]);

        $this->snapshotVersion($verifiedByUserId);
    }

    /**
     * Corrects a previously verified report. The pre-amendment payload is
     * already preserved by the version snapshot markVerified() took, so
     * it's safe to overwrite structured_data_payload here and snapshot the
     * new (amended) content — nothing is lost either way.
     */
    public function markAmended(int $userId, string $justification, array $newPayload): void
    {
        $this->requireStatus(self::STATUS_VERIFIED);

        $this->update([
            'status' => self::STATUS_AMENDED,
            'structured_data_payload' => $newPayload,
        ]);

        $this->snapshotVersion($userId, $justification);
    }

    /**
     * Reports are never overwritten in place (Pillar 5) — every meaningful change
     * writes an immutable snapshot here. The full "block direct edits, route
     * everything through this" enforcement lands with the reporting UI in a
     * later chunk; this is the base recording mechanism.
     */
    public function snapshotVersion(int $modifierUserId, ?string $justification = null): ReportVersion
    {
        return $this->versions()->create([
            'modifier_user_id' => $modifierUserId,
            'status' => $this->status,
            'historical_payload_snapshot' => $this->structured_data_payload,
            'amendment_justification_reason' => $justification,
        ]);
    }
}
