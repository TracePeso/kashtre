<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ImagingStudy extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'accession_number',
        'study_instance_uid',
        'orthanc_study_id',
        'orthanc_worklist_id',
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'main_room_id',
        'performed_by_user_id',
        'imaging_order_id',
        'protocol_code',
        'modality_type',
        'priority',
        'status',
        'main_module_status',
        'preparation_required',
        'preparation_complete',
        'readiness_check_results',
        'consent_verified',
        'consent_verified_by_user_id',
        'consent_verified_at',
        'consent_notes',
        'status_history',
        'is_consumption_executed',
        'consumption_executed_at',
        'ready_for_study_at',
        'image_acquired_at',
        'verified_at',
    ];

    protected $casts = [
        'preparation_required' => 'boolean',
        'preparation_complete' => 'boolean',
        'readiness_check_results' => 'array',
        'consent_verified' => 'boolean',
        'consent_verified_at' => 'datetime',
        'status_history' => 'array',
        'is_consumption_executed' => 'boolean',
        'consumption_executed_at' => 'datetime',
        'ready_for_study_at' => 'datetime',
        'image_acquired_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // Internal RIS lifecycle — see spec Pillar 1 / technical blueprint section 3.
    const STATUS_ORDER_RECEIVED = 'ORDER_RECEIVED';
    const STATUS_PREPARATION_REQUIRED = 'PREPARATION_REQUIRED';
    const STATUS_PREPARATION_COMPLETE = 'PREPARATION_COMPLETE';
    const STATUS_READY_FOR_STUDY = 'READY_FOR_STUDY';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_IMAGE_ACQUIRED = 'IMAGE_ACQUIRED';
    const STATUS_REPORT_PENDING = 'REPORT_PENDING';
    const STATUS_REPORTED = 'REPORTED';
    const STATUS_VERIFIED = 'VERIFIED';

    const STATUSES = [
        self::STATUS_ORDER_RECEIVED,
        self::STATUS_PREPARATION_REQUIRED,
        self::STATUS_PREPARATION_COMPLETE,
        self::STATUS_READY_FOR_STUDY,
        self::STATUS_IN_PROGRESS,
        self::STATUS_IMAGE_ACQUIRED,
        self::STATUS_REPORT_PENDING,
        self::STATUS_REPORTED,
        self::STATUS_VERIFIED,
    ];

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected static function booted()
    {
        static::creating(function (ImagingStudy $study) {
            $study->uuid = $study->uuid ?: (string) Str::uuid();

            if (empty($study->status)) {
                $study->status = self::STATUS_ORDER_RECEIVED;
            }

            if (empty($study->priority)) {
                $study->priority = self::PRIORITY_NORMAL;
            }

            if (empty($study->accession_number)) {
                $study->accession_number = self::generateAccessionNumber((int) $study->business_id);
            }
        });
    }

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function imagingOrder()
    {
        return $this->belongsTo(ImagingOrder::class);
    }

    public function reports()
    {
        return $this->hasMany(ImagingReport::class);
    }

    public function radiationExposureLogs()
    {
        return $this->hasMany(RadiationExposureLog::class);
    }

    public function consumptions()
    {
        return $this->hasMany(ImagingConsumption::class);
    }

    public function contrastAdministrations()
    {
        return $this->hasMany(ContrastAdministration::class);
    }

    /**
     * Pillar 13: gates the study page's Radiation Exposure card. Driven by
     * the protocol's own explicit toggle (an admin-controlled choice) rather
     * than a hardcoded modality name match — some protocols on an otherwise
     * ionizing modality may not need dose tracking, and vice versa.
     */
    public function isIonizingModality(): bool
    {
        return (bool) ($this->protocol()?->involves_ionizing_radiation ?? false);
    }

    public function recoveryRecord()
    {
        return $this->hasOne(RecoveryRecord::class);
    }

    /**
     * RIS Amendment v2.6, Chunk 3/4: every workflow execution this study has
     * had (normally exactly one — a new one would only appear if a study
     * were ever re-run through a workflow, which nothing does today).
     */
    public function workflowExecutions()
    {
        return $this->hasMany(ImagingStudyWorkflowExecution::class, 'imaging_study_id');
    }

    /**
     * The execution actually driving this study's current position, if one
     * exists yet (lazily created by WorkflowEngineService on first mark*()
     * call for studies that predate Chunk 3) — used by the My Queue page
     * and WorkflowOwnershipService to resolve claims.
     */
    public function activeWorkflowExecution(): ?ImagingStudyWorkflowExecution
    {
        return $this->workflowExecutions()->active()->latest()->first();
    }

    public function requiresRecovery(): bool
    {
        return (bool) $this->protocol()?->requires_recovery;
    }

    public function isDischargeCleared(): bool
    {
        return (bool) $this->recoveryRecord?->isDischarged();
    }

    /**
     * Pillar 15: Prior Study Comparison — other studies for the same client
     * under the same protocol (a reasonable proxy for "same anatomical
     * location/study type" without a dedicated taxonomy), most recent first.
     */
    public function priorStudies()
    {
        return self::query()
            ->where('business_id', $this->business_id)
            ->where('client_id', $this->client_id)
            ->where('protocol_code', $this->protocol_code)
            ->where('id', '!=', $this->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Client is referenced by its permanent business-scoped code (Client::client_id),
     * not a FK, so this is a lookup rather than a real Eloquent relation.
     */
    public function resolveClient(): ?Client
    {
        return Client::where('business_id', $this->business_id)
            ->where('client_id', $this->client_id)
            ->first();
    }

    public function resolveRoom(): ?Room
    {
        return $this->main_room_id
            ? Room::find($this->main_room_id)
            : null;
    }

    /**
     * Pillar 1.1: the DICOM AE Title for this study's routed hardware, if
     * an admin has configured one — null just means "not configured yet",
     * not an error (matches the PACS stub's "null = not available" idiom).
     */
    public function resolveHardwareAeTitle(): ?string
    {
        if (! $this->main_room_id) {
            return null;
        }

        return ImagingServicePointConfig::where('business_id', $this->business_id)
            ->where('main_room_id', $this->main_room_id)
            ->value('hardware_ae_title');
    }

    /**
     * The clinician who originally ordered this study (Pillar 6 critical
     * finding alerts route here) — via the order, not a direct column.
     */
    public function resolveOrderingUser(): ?User
    {
        return $this->imagingOrder?->ordering_user_id
            ? User::find($this->imagingOrder->ordering_user_id)
            : null;
    }

    /**
     * Protocol is referenced by its code, not a FK — a lookup, not a relation.
     */
    public function protocol(): ?ImagingProtocol
    {
        return ImagingProtocol::where('code', $this->protocol_code)->first();
    }

    // Pillar 3: Preparation & Readiness Verification. Both checklists read
    // from the protocol's configured item lists and are ticked off against
    // the same readiness_check_results map, keyed by item name.
    public function preparationChecklistItems(): array
    {
        return $this->protocol()?->preparation_requirements ?? [];
    }

    public function readinessChecklistItems(): array
    {
        return $this->protocol()?->readiness_checks ?? [];
    }

    protected function isChecklistSatisfied(array $items): bool
    {
        if (empty($items)) {
            return true;
        }

        $results = $this->readiness_check_results ?? [];

        foreach ($items as $item) {
            if (empty($results[$item])) {
                return false;
            }
        }

        return true;
    }

    /**
     * RIS Amendment v2.6, Chunk 5: the workflow step a target RIS status
     * maps to, for THIS study's protocol's active workflow — null if the
     * protocol has no persisted workflow at all (an unsaved/in-memory
     * ImagingProtocol, as plain unit tests use, or a real protocol nobody
     * has configured a workflow for yet). The three isXSatisfied() methods
     * below use this to prefer CompletionRuleService's rule-engine check
     * when a real step exists, falling back to the exact pre-Chunk-5 direct
     * protocol-field check otherwise — so a study with no real workflow
     * behaves identically to before, and every existing real protocol
     * (backfilled with a workflow since Chunk 2, kept in sync since Chunk 5
     * by ImagingProtocol::syncLegacyCompletionRules()) is enforced through
     * the one rule engine instead.
     */
    protected function resolveWorkflowStepByRisStatus(string $risStatus): ?ProtocolWorkflowStep
    {
        return $this->protocol()?->activeWorkflow()?->steps()->where('ris_status', $risStatus)->first();
    }

    /**
     * true, or an array of CompletionRuleService-shaped error rows —
     * markPreparationComplete() uses the array form to build a specific
     * message and to check override eligibility; isPreparationChecklistSatisfied()
     * just collapses it to a bool.
     */
    public function validatePreparationChecklist(): bool|array
    {
        $step = $this->resolveWorkflowStepByRisStatus(self::STATUS_PREPARATION_COMPLETE);

        if ($step) {
            return app(\App\Services\Imaging\CompletionRuleService::class)->validateStepCompletion($this, $step);
        }

        return $this->isChecklistSatisfied($this->preparationChecklistItems()) ? true : [[
            'message' => 'Preparation Checklist',
            'allow_override' => false,
            'authorized_override_permissions' => [],
        ]];
    }

    public function isPreparationChecklistSatisfied(): bool
    {
        return $this->validatePreparationChecklist() === true;
    }

    public function validateReadinessChecklist(): bool|array
    {
        $step = $this->resolveWorkflowStepByRisStatus(self::STATUS_READY_FOR_STUDY);

        if ($step) {
            return app(\App\Services\Imaging\CompletionRuleService::class)->validateStepCompletion(
                $this, $step, \App\Models\ImagingWorkflowStepCompletionRule::TYPE_CHECKLIST
            );
        }

        return $this->isChecklistSatisfied($this->readinessChecklistItems()) ? true : [[
            'message' => 'Readiness Checklist',
            'allow_override' => false,
            'authorized_override_permissions' => [],
        ]];
    }

    public function isReadinessChecklistSatisfied(): bool
    {
        return $this->validateReadinessChecklist() === true;
    }

    public function validateConsent(): bool|array
    {
        $step = $this->resolveWorkflowStepByRisStatus(self::STATUS_READY_FOR_STUDY);

        if ($step) {
            return app(\App\Services\Imaging\CompletionRuleService::class)->validateStepCompletion(
                $this, $step, \App\Models\ImagingWorkflowStepCompletionRule::TYPE_SIGNATURE
            );
        }

        return (! ($this->protocol()?->requires_consent) || $this->consent_verified) ? true : [[
            'message' => 'Consent Verification',
            'allow_override' => false,
            'authorized_override_permissions' => [],
        ]];
    }

    public function isConsentSatisfied(): bool
    {
        return $this->validateConsent() === true;
    }

    public function canMarkPreparationComplete(): bool
    {
        return $this->isStatus(self::STATUS_PREPARATION_REQUIRED) && $this->isPreparationChecklistSatisfied();
    }

    /**
     * A protocol can be configured to skip the Preparation phase entirely
     * (ImagingProtocol::requires_preparation) — for protocols that don't
     * follow the standard flow, studies then jump from ORDER_RECEIVED
     * straight to READY_FOR_STUDY. Readiness checks and consent still
     * apply either way; only the Preparation status hop is bypassed.
     */
    protected function expectedStatusBeforeReadyForStudy(): string
    {
        return ($this->protocol()?->requires_preparation ?? true)
            ? self::STATUS_PREPARATION_COMPLETE
            : self::STATUS_ORDER_RECEIVED;
    }

    public function canMarkReadyForStudy(): bool
    {
        return $this->isStatus($this->expectedStatusBeforeReadyForStudy())
            && $this->isReadinessChecklistSatisfied()
            && $this->isConsentSatisfied();
    }

    /**
     * Replace the checked/unchecked state of every known checklist item.
     * $checkedItems is the subset of preparationChecklistItems()+readinessChecklistItems()
     * that should be marked true; everything else in those lists is set false.
     */
    public function updateChecklistResults(array $checkedItems): void
    {
        $allItems = array_unique(array_merge(
            $this->preparationChecklistItems(),
            $this->readinessChecklistItems()
        ));

        $results = [];
        foreach ($allItems as $item) {
            $results[$item] = in_array($item, $checkedItems, true);
        }

        $this->update(['readiness_check_results' => $results]);
    }

    public function verifyConsent(int $verifiedByUserId, ?string $notes = null): void
    {
        $this->update([
            'consent_verified' => true,
            'consent_verified_by_user_id' => $verifiedByUserId,
            'consent_verified_at' => now(),
            'consent_notes' => $notes,
        ]);
    }

    /**
     * consent_verified_by_user_id is a plain indexed column, not a FK
     * (same cross-domain-decoupling rule as elsewhere on this model).
     */
    public function resolveConsentVerifiedBy(): ?User
    {
        return $this->consent_verified_by_user_id
            ? User::find($this->consent_verified_by_user_id)
            : null;
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeAwaitingReport($query)
    {
        return $query->where('status', self::STATUS_REPORT_PENDING);
    }

    public function scopeAwaitingVerification($query)
    {
        return $query->where('status', self::STATUS_REPORTED);
    }

    // Status checks
    public function isStatus(string $status): bool
    {
        return $this->status === $status;
    }

    // Transitions — each records a Pillar 10 procedure-record entry. Every
    // method guards both the status sequence and (where relevant) the
    // Pillar 3 readiness gates, mirroring ImagingOrder::acceptIntoStudy()'s
    // guard-clause style.
    protected function requireStatus(string $expected): void
    {
        if (! $this->isStatus($expected)) {
            throw new \RuntimeException(
                "Study {$this->accession_number} is '{$this->status}', expected '{$expected}' for this transition."
            );
        }
    }

    /**
     * RIS Amendment v2.6, Chunk 3: this and every other mark*() method below
     * keep their exact original name/signature/guard/side-effect ordering —
     * only the actual status write is delegated to
     * WorkflowEngineService::completeStep(), which looks up the workflow
     * step whose ris_status matches the literal string passed here and
     * applies it via this same transitionTo() this method used to call
     * directly. Since the backfilled default workflow's ris_status values
     * are the exact same strings as these STATUS_* constants, this produces
     * a byte-identical $status/status_history result to before — every
     * existing caller (ImagingReportObserver, ImagingStudyController,
     * RecoveryRecord) needs zero changes.
     */
    public function markPreparationRequired(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_ORDER_RECEIVED);

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_PREPARATION_REQUIRED, $userId, ['preparation_required' => true]
        );
    }

    /**
     * RIS Amendment v2.6, Chunk 5: throws with a message built from every
     * failing rule ("Cannot Complete Step — Missing: X, Y") unless
     * $overrideReason is given and $userId holds an authorized override
     * permission for every one of them — in which case the block is
     * bypassed and audited via CompletionRuleService::recordOverride()
     * instead of thrown.
     */
    protected function enforceOrOverride(array $errors, ?int $userId, ?string $overrideReason, string $targetRisStatus): void
    {
        $ruleService = app(\App\Services\Imaging\CompletionRuleService::class);

        if ($userId && $overrideReason && $ruleService->userCanOverride($errors, $userId)) {
            $step = $this->resolveWorkflowStepByRisStatus($targetRisStatus);

            if ($step) {
                $ruleService->recordOverride($this, $step, $userId, $overrideReason);

                return;
            }
        }

        throw new \RuntimeException(
            'Cannot complete step — Missing: ' . collect($errors)->pluck('message')->implode(', ')
        );
    }

    public function markPreparationComplete(?int $userId = null, ?string $overrideReason = null): void
    {
        $this->requireStatus(self::STATUS_PREPARATION_REQUIRED);

        $errors = $this->validatePreparationChecklist();

        if ($errors !== true) {
            $this->enforceOrOverride($errors, $userId, $overrideReason, self::STATUS_PREPARATION_COMPLETE);
        }

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_PREPARATION_COMPLETE, $userId, ['preparation_complete' => true]
        );
    }

    public function markReadyForStudy(?int $userId = null, ?string $overrideReason = null): void
    {
        $this->requireStatus($this->expectedStatusBeforeReadyForStudy());

        $readinessErrors = $this->validateReadinessChecklist();

        if ($readinessErrors !== true) {
            $this->enforceOrOverride($readinessErrors, $userId, $overrideReason, self::STATUS_READY_FOR_STUDY);
        }

        $consentErrors = $this->validateConsent();

        if ($consentErrors !== true) {
            $this->enforceOrOverride($consentErrors, $userId, $overrideReason, self::STATUS_READY_FOR_STUDY);
        }

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_READY_FOR_STUDY, $userId, ['ready_for_study_at' => now()]
        );

        // Pillar 1.1: the exact trigger point for the DICOM Modality
        // Worklist broadcast. The job itself is queued (ShouldQueue) and
        // its broker is stubbed (DicomWorklistBroker -> LoggingDicomWorklistBroker
        // in AppServiceProvider) until real scanner hardware exists.
        \App\Jobs\BroadcastModalityWorklist::dispatch($this->id);
    }

    public function markInProgress(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_READY_FOR_STUDY);

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_IN_PROGRESS, $userId, array_filter(['performed_by_user_id' => $userId])
        );
    }

    public function markImageAcquired(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_IN_PROGRESS);

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_IMAGE_ACQUIRED, $userId, ['image_acquired_at' => now()]
        );
    }

    /**
     * RIS Amendment v2.6, Chunk 6: as of this chunk, the three status-based
     * triggers (Preparation Complete / In Progress / Image Acquired) no
     * longer call this — WorkflowEngineService::completeStep() fires
     * consumption directly whenever the step it just reached has
     * triggers_consumption set (Chunk 2), which is what
     * ImagingProtocol::consumption_trigger_status used to gate here. That
     * column stays present for now (removed in a later cleanup pass) but
     * this method's only remaining caller is
     * RecoveryRecord::clearForDischarge()'s RECOVERY_COMPLETE case, since
     * recovery discharge isn't itself a workflow step the engine could
     * attach a trigger to.
     */
    public function triggerConsumptionIfDue(string $reachedTrigger, ?int $userId = null): void
    {
        if ($this->is_consumption_executed) {
            return;
        }

        $configuredTrigger = $this->protocol()?->consumption_trigger_status ?? ImagingProtocol::TRIGGER_IMAGE_ACQUIRED;

        if ($configuredTrigger !== $reachedTrigger) {
            return;
        }

        app(\App\Services\Imaging\RadiologyRecipeEngine::class)->depleteForStudy($this, $userId);

        $this->update([
            'is_consumption_executed' => true,
            'consumption_executed_at' => now(),
        ]);
    }

    public function markReportPending(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_IMAGE_ACQUIRED);

        // Pillar 16: sedation/interventional protocols lock the final
        // documentation step until recovery monitoring + discharge
        // clearance are logged.
        if ($this->requiresRecovery() && ! $this->isDischargeCleared()) {
            throw new \RuntimeException('Recovery monitoring and discharge clearance must be completed before this study can move to reporting.');
        }

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_REPORT_PENDING, $userId
        );
    }

    public function markReported(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_REPORT_PENDING);

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_REPORTED, $userId
        );
    }

    public function markVerified(?int $userId = null): void
    {
        $this->requireStatus(self::STATUS_REPORTED);

        app(\App\Services\Imaging\WorkflowEngineService::class)->completeStep(
            $this, self::STATUS_VERIFIED, $userId, ['verified_at' => now()]
        );
    }

    /**
     * Public (widened from protected in RIS Amendment v2.6, Chunk 3) so
     * WorkflowStatusMapperService can drive this study's status from the
     * new Workflow Engine while reusing this exact, already-tested
     * history-logging mechanism rather than duplicating it.
     */
    public function transitionTo(string $status, ?int $userId, array $extra = []): void
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'status' => $status,
            'at' => now()->toIso8601String(),
            'user_id' => $userId,
        ];

        $this->update(array_merge($extra, [
            'status' => $status,
            'status_history' => $history,
        ]));
    }

    /**
     * Deterministic-format, collision-checked accession number.
     * Same retry idiom as Client::generateClientId().
     */
    public static function generateAccessionNumber(int $businessId): string
    {
        $year = now()->format('Y');
        $baseSequence = self::where('business_id', $businessId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        $attempt = 0;
        $maxAttempts = 50;

        do {
            $candidate = self::buildAccessionCandidate($year, $baseSequence + $attempt);

            if (! self::where('accession_number', $candidate)->exists()) {
                return $candidate;
            }

            $attempt++;
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException('Unable to generate a unique accession number after multiple attempts.');
    }

    /**
     * Pure formatting, split out so it can be unit tested without touching the DB.
     */
    public static function buildAccessionCandidate(string $year, int $sequence): string
    {
        return sprintf('ACC-%s-%06d', $year, $sequence);
    }
}
