<?php

namespace App\Services;

use App\Models\HrAttendanceLedger;
use App\Models\HrBiometricVerification;
use App\Models\HrDutyRosterEntry;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HybridAttendanceLedgerService
{
    private const DOUBLE_PUNCH_WINDOW_MINUTES = 5;

    public function recordFromVerification(HrBiometricVerification $verification, ?string $punchType = null): ?HrAttendanceLedger
    {
        if (! $verification->passed() || ! $verification->staff_assignment_id) {
            return null;
        }

        $verification->loadMissing('staffAssignment');

        if (! $verification->staffAssignment) {
            return null;
        }

        $punchType = $this->normalizePunchType($punchType ?? $verification->event_type ?? ($verification->metadata['punch_type'] ?? null))
            ?? HrAttendanceLedger::PUNCH_IN;

        return $this->recordPunch(
            $verification->staffAssignment,
            $punchType,
            [
                'verification' => $verification,
                'provider' => $verification->provider ?: 'local',
                'device_id' => $verification->device_id,
                'source_event_id' => $verification->source_event_id,
                'occurred_at' => $verification->verified_at ?: now(),
                'metadata' => [
                    'modality' => $verification->modality,
                    'verification_uuid' => $verification->uuid,
                    'verification_event_type' => $verification->event_type,
                ],
            ]
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordPunch(StaffAssignment $staffAssignment, string $punchType, array $context = []): HrAttendanceLedger
    {
        $punchType = $this->normalizePunchType($punchType) ?? HrAttendanceLedger::PUNCH_IN;
        $occurredAt = $this->carbon($context['occurred_at'] ?? null);
        $provider = $this->cleanString($context['provider'] ?? null) ?: 'local';
        $deviceId = $this->cleanString($context['device_id'] ?? null);
        $sourceEventId = $this->truncateSourceEventId($this->cleanString($context['source_event_id'] ?? null));
        $verification = $context['verification'] ?? null;
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $lateWarningCount = null;

        $ledger = DB::transaction(function () use (
            $staffAssignment,
            $punchType,
            $occurredAt,
            $provider,
            $deviceId,
            $sourceEventId,
            $verification,
            $metadata,
            &$lateWarningCount
        ): HrAttendanceLedger {
            if ($sourceEventId) {
                $existing = HrAttendanceLedger::query()
                    ->where('organization_id', $staffAssignment->organization_id)
                    ->where('provider', $provider)
                    ->where('source_event_id', $sourceEventId)
                    ->when(
                        $deviceId,
                        fn ($query) => $query->where('device_id', $deviceId),
                        fn ($query) => $query->whereNull('device_id')
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $rosterContext = $this->rosterContext($staffAssignment, $occurredAt);
            $duplicate = $this->recentSamePunch($staffAssignment, $punchType, $occurredAt);
            $lateClockIn = $this->lateClockInContext($staffAssignment, $punchType, $occurredAt, $rosterContext);
            $ledger = HrAttendanceLedger::create([
                'organization_id' => $staffAssignment->organization_id,
                'staff_assignment_id' => $staffAssignment->id,
                'staff_uuid' => $staffAssignment->staff_uuid,
                'hr_biometric_verification_id' => $verification instanceof HrBiometricVerification ? $verification->id : null,
                'roster_entry_id' => $rosterContext['roster_entry_id'],
                'client_space_unit_id' => $rosterContext['client_space_unit_id'],
                'shift_type_id' => $rosterContext['shift_type_id'],
                'punch_type' => $punchType,
                'punch_source' => $this->punchSource($provider, $deviceId),
                'provider' => $provider,
                'device_id' => $deviceId,
                'source_event_id' => $sourceEventId,
                'occurred_at' => $occurredAt,
                'status' => $duplicate ? HrAttendanceLedger::STATUS_IGNORED : HrAttendanceLedger::STATUS_OPEN,
                'is_late_clock_in' => (bool) ($lateClockIn['is_late_clock_in'] ?? false),
                'minutes_late' => $lateClockIn['minutes_late'] ?? null,
                'is_late_flagged' => false,
                'ignored_reason' => $duplicate ? 'Duplicate same staff and punch type within 5 minutes.' : null,
                'metadata' => array_filter(array_merge($metadata, [
                    'double_punch_window_minutes' => self::DOUBLE_PUNCH_WINDOW_MINUTES,
                    'duplicate_of_ledger_uuid' => $duplicate?->uuid,
                ], $lateClockIn['metadata'] ?? []), fn ($value) => $value !== null && $value !== ''),
            ]);

            if ($ledger->isIgnored()) {
                return $ledger;
            }

            if ($ledger->isLateClockIn()) {
                $lateCount = $this->lateClockInCount($staffAssignment);
                $lateFlagTriggerCount = $this->lateFlagTriggerCount($staffAssignment);
                $lateMetadata = array_filter(array_merge($ledger->metadata ?? [], [
                    'late_occurrence_count' => $lateCount,
                    'late_flag_trigger_count' => $lateFlagTriggerCount,
                ]), fn ($value) => $value !== null && $value !== '');

                $ledger->forceFill([
                    'is_late_flagged' => $lateCount >= $lateFlagTriggerCount,
                    'metadata' => $lateMetadata,
                ])->save();

                if ($ledger->is_late_flagged) {
                    $lateWarningCount = $lateCount;
                }
            }

            if ($punchType === HrAttendanceLedger::PUNCH_OUT) {
                $this->pairWithOpenInPunch($ledger);
                app(HolidayLeaveCreditService::class)->createForPairedPunch($ledger->refresh());
            }

            return $ledger->refresh();
        });

        if ($lateWarningCount !== null) {
            app(LateAttendanceNotificationService::class)->notifyIfNeeded($ledger, $lateWarningCount);
        }

        return $ledger;
    }

    private function lateClockInContext(StaffAssignment $staffAssignment, string $punchType, Carbon $occurredAt, array $rosterContext): array
    {
        if ($punchType !== HrAttendanceLedger::PUNCH_IN || ! filled($rosterContext['shift_type_id'] ?? null)) {
            return [];
        }

        $organization = Organization::query()->find($staffAssignment->organization_id);
        $threshold = $organization?->biometric_late_clock_in_threshold_minutes;

        if (! $organization?->biometric_late_clock_in_enabled || $threshold === null || $threshold < 1) {
            return [];
        }

        $shiftType = ShiftType::query()->find($rosterContext['shift_type_id']);

        if (! $shiftType?->start_time) {
            return [];
        }

        $scheduledStart = Carbon::parse($occurredAt->toDateString().' '.$shiftType->start_time);

        if ($occurredAt->lte($scheduledStart)) {
            return [
                'metadata' => [
                    'scheduled_start_at' => $scheduledStart->toDateTimeString(),
                    'late_threshold_minutes' => (int) $threshold,
                ],
            ];
        }

        $minutesLate = $scheduledStart->diffInMinutes($occurredAt);

        return [
            'is_late_clock_in' => $minutesLate > (int) $threshold,
            'minutes_late' => $minutesLate,
            'metadata' => [
                'scheduled_start_at' => $scheduledStart->toDateTimeString(),
                'late_threshold_minutes' => (int) $threshold,
            ],
        ];
    }

    private function lateClockInCount(StaffAssignment $staffAssignment): int
    {
        return HrAttendanceLedger::query()
            ->where('organization_id', $staffAssignment->organization_id)
            ->where('staff_assignment_id', $staffAssignment->id)
            ->where('punch_type', HrAttendanceLedger::PUNCH_IN)
            ->where('is_late_clock_in', true)
            ->where('status', '!=', HrAttendanceLedger::STATUS_IGNORED)
            ->count();
    }

    private function lateFlagTriggerCount(StaffAssignment $staffAssignment): int
    {
        $organization = Organization::query()->find($staffAssignment->organization_id);
        $repeatCount = (int) ($organization?->biometric_late_clock_in_repeat_count ?? 0);

        return $repeatCount >= 1 ? $repeatCount : 3;
    }

    public function normalizePunchType(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (in_array($value, ['in', 'checkin', 'check-in', 'clockin', 'clock-in', 'entry', 'enter', 'entrance', '0'], true)) {
            return HrAttendanceLedger::PUNCH_IN;
        }

        if (in_array($value, ['out', 'checkout', 'check-out', 'clockout', 'clock-out', 'exit', 'leave', '1'], true)) {
            return HrAttendanceLedger::PUNCH_OUT;
        }

        if (str_contains($value, 'out') || str_contains($value, 'exit')) {
            return HrAttendanceLedger::PUNCH_OUT;
        }

        if (str_contains($value, 'in') || str_contains($value, 'entry')) {
            return HrAttendanceLedger::PUNCH_IN;
        }

        return null;
    }

    private function recentSamePunch(StaffAssignment $staffAssignment, string $punchType, Carbon $occurredAt): ?HrAttendanceLedger
    {
        return HrAttendanceLedger::query()
            ->where('organization_id', $staffAssignment->organization_id)
            ->where('staff_assignment_id', $staffAssignment->id)
            ->where('punch_type', $punchType)
            ->whereIn('status', [HrAttendanceLedger::STATUS_OPEN, HrAttendanceLedger::STATUS_PAIRED])
            ->whereBetween('occurred_at', [
                $occurredAt->copy()->subMinutes(self::DOUBLE_PUNCH_WINDOW_MINUTES),
                $occurredAt->copy()->addMinutes(self::DOUBLE_PUNCH_WINDOW_MINUTES),
            ])
            ->orderBy('occurred_at')
            ->lockForUpdate()
            ->first();
    }

    private function pairWithOpenInPunch(HrAttendanceLedger $outPunch): void
    {
        $inPunch = HrAttendanceLedger::query()
            ->where('organization_id', $outPunch->organization_id)
            ->where('staff_assignment_id', $outPunch->staff_assignment_id)
            ->where('punch_type', HrAttendanceLedger::PUNCH_IN)
            ->where('status', HrAttendanceLedger::STATUS_OPEN)
            ->where('occurred_at', '<=', $outPunch->occurred_at)
            ->latest('occurred_at')
            ->lockForUpdate()
            ->first();

        if (! $inPunch) {
            return;
        }

        $inPunch->forceFill([
            'paired_with_id' => $outPunch->id,
            'status' => HrAttendanceLedger::STATUS_PAIRED,
        ])->save();

        $outPunch->forceFill([
            'paired_with_id' => $inPunch->id,
            'status' => HrAttendanceLedger::STATUS_PAIRED,
        ])->save();
    }

    /**
     * @return array{roster_entry_id: ?int, client_space_unit_id: ?int, shift_type_id: ?int}
     */
    private function rosterContext(StaffAssignment $staffAssignment, Carbon $occurredAt): array
    {
        $entry = HrDutyRosterEntry::query()
            ->with('dutyRoster')
            ->where('organization_id', $staffAssignment->organization_id)
            ->where('staff_assignment_id', $staffAssignment->id)
            ->whereDate('roster_date', $occurredAt->toDateString())
            ->whereHas('dutyRoster', fn ($query) => $query->where('status', '!=', 'archived'))
            ->latest('id')
            ->first();

        return [
            'roster_entry_id' => $entry?->id,
            'client_space_unit_id' => $entry?->dutyRoster?->organizational_unit_id,
            'shift_type_id' => $entry?->shift_type_id,
        ];
    }

    private function punchSource(string $provider, ?string $deviceId): string
    {
        return $deviceId ? "{$provider}:{$deviceId}" : $provider;
    }

    private function carbon(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function truncateSourceEventId(?string $value): ?string
    {
        if (! $value || strlen($value) <= 191) {
            return $value;
        }

        return 'hash:' . hash('sha256', $value);
    }
}
