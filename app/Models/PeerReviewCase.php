<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeerReviewCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_id',
        'imaging_report_id',
        'original_author_user_id',
        'reviewer_user_id',
        'qa_status',
        'reviewer_payload',
        'variation_score',
        'reviewer_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewer_payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    const QA_STATUS_AWAITING_BLIND_READ = 'AWAITING_BLIND_READ';
    const QA_STATUS_COMPLETED = 'COMPLETED';

    const VARIATION_CONCORDANT = 'CONCORDANT';
    const VARIATION_MINOR_DISCORDANCE = 'MINOR_DISCORDANCE';
    const VARIATION_MAJOR_DISCORDANCE = 'MAJOR_DISCORDANCE';

    const VARIATION_SCORES = [
        self::VARIATION_CONCORDANT,
        self::VARIATION_MINOR_DISCORDANCE,
        self::VARIATION_MAJOR_DISCORDANCE,
    ];

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function imagingReport()
    {
        return $this->belongsTo(ImagingReport::class);
    }

    public function resolveOriginalAuthor(): ?User
    {
        return User::find($this->original_author_user_id);
    }

    public function resolveReviewer(): ?User
    {
        return $this->reviewer_user_id ? User::find($this->reviewer_user_id) : null;
    }

    public function scopeAwaitingReview($query)
    {
        return $query->where('qa_status', self::QA_STATUS_AWAITING_BLIND_READ);
    }

    public function isStatus(string $status): bool
    {
        return $this->qa_status === $status;
    }

    /**
     * Pure boundary check, split out from the observer's random_int() call
     * so the rate logic can be unit tested without touching randomness.
     */
    public static function shouldTrigger(int $ratePercent, int $roll): bool
    {
        return $roll <= $ratePercent;
    }

    public function markCompleted(int $reviewerId, string $variationScore, ?string $notes, array $reviewerPayload): void
    {
        if (! $this->isStatus(self::QA_STATUS_AWAITING_BLIND_READ)) {
            throw new \RuntimeException("Peer review case #{$this->id} is not awaiting a blind read.");
        }

        if ($reviewerId === $this->original_author_user_id) {
            throw new \RuntimeException('The original reporting radiologist cannot complete their own peer review.');
        }

        $businessId = (int) $this->imagingStudy?->business_id;

        if ($businessId && ! ImagingModuleConfig::isEligibleReviewer($businessId, $reviewerId)) {
            throw new \RuntimeException('You are not in the configured reviewer pool for this business.');
        }

        if (! in_array($variationScore, self::VARIATION_SCORES, true)) {
            throw new \RuntimeException("Invalid variation score '{$variationScore}'.");
        }

        $this->update([
            'qa_status' => self::QA_STATUS_COMPLETED,
            'reviewer_user_id' => $reviewerId,
            'variation_score' => $variationScore,
            'reviewer_notes' => $notes,
            'reviewer_payload' => $reviewerPayload,
            'reviewed_at' => now(),
        ]);
    }
}
