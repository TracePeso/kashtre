<?php

namespace App\Http\Controllers\API\V1\Time;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\LocalDateTime;
use App\Domain\Time\ValueObjects\TimeContext;
use App\Domain\Time\ValueObjects\UtcInstant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeEngineController extends Controller
{
    public function now(SharedTimeGateway $time): JsonResponse
    {
        $instant = $time->now();

        return response()->json([
            'data' => [
                'utc' => $instant->toIso8601(),
                'apiVersion' => config('time.api_version'),
                'enabled' => $time->enabled(),
            ],
        ]);
    }

    public function timezones(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $rows = $time->catalogue()->search($request->query('q'));

        return response()->json([
            'data' => $rows->map(fn ($z) => [
                'publicId' => $z->public_id,
                'ianaId' => $z->iana_id,
                'displayName' => $z->display_name,
                'regionCode' => $z->region_code,
                'canonicalIanaId' => $z->canonicalId(),
                'status' => $z->status,
                'tzdbRelease' => $z->tzdb_release,
            ])->values(),
        ]);
    }

    public function resolve(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'tenantId' => ['nullable', 'string', 'max:64'],
            'branchId' => ['nullable', 'string', 'max:64'],
            'facilityId' => ['nullable', 'string', 'max:64'],
            'userId' => ['nullable', 'string', 'max:64'],
            'deviceId' => ['nullable', 'string', 'max:64'],
            'purpose' => ['nullable', 'string', 'max:24'],
            'asOf' => ['nullable', 'date'],
        ]);

        $purpose = PolicyPurpose::tryFrom($data['purpose'] ?? 'OPERATIONAL') ?? PolicyPurpose::OPERATIONAL;
        $asOf = isset($data['asOf']) ? UtcInstant::fromString($data['asOf']) : null;

        $resolved = $time->resolution()->resolve(new TimeContext(
            tenantId: $data['tenantId'] ?? $this->tenantKey($request),
            branchId: $data['branchId'] ?? null,
            facilityId: $data['facilityId'] ?? null,
            userId: $data['userId'] ?? null,
            deviceId: $data['deviceId'] ?? null,
            purpose: $purpose,
            asOf: $asOf,
        ), $data['tenantId'] ?? $this->tenantKey($request));

        return response()->json(['data' => $resolved->toArray()]);
    }

    public function snapshot(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'ianaId' => ['required', 'string', 'max:64'],
            'asOf' => ['nullable', 'date'],
        ]);

        $asOf = isset($data['asOf']) ? UtcInstant::fromString($data['asOf']) : null;

        return response()->json([
            'data' => $time->snapshot($data['ianaId'], $asOf)->toArray(),
        ]);
    }

    public function businessDate(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'tenantId' => ['nullable', 'string', 'max:64'],
            'asOf' => ['nullable', 'date'],
            'rolloverOffsetMinutes' => ['nullable', 'integer'],
        ]);

        $tenant = $data['tenantId'] ?? $this->tenantKey($request);
        $asOf = isset($data['asOf']) ? UtcInstant::fromString($data['asOf']) : null;
        $date = $time->businessDates()->businessDateFor(
            $asOf,
            new TimeContext(tenantId: $tenant),
            $tenant,
            $data['rolloverOffsetMinutes'] ?? null,
        );

        return response()->json([
            'data' => [
                'businessDate' => $date->toString(),
                'tenantId' => $tenant,
            ],
        ]);
    }

    public function convertLocal(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'local' => ['required', 'string'],
            'ianaId' => ['required', 'string', 'max:64'],
            'gapPolicy' => ['nullable', 'string', 'max:24'],
            'overlapPolicy' => ['nullable', 'string', 'max:24'],
        ]);

        try {
            $local = LocalDateTime::parse($data['local']);
            $result = $time->civil()->localToUtc(
                $local,
                $data['ianaId'],
                $data['gapPolicy'] ?? 'SHIFT_FORWARD',
                $data['overlapPolicy'] ?? 'EARLIER',
            );

            return response()->json([
                'data' => [
                    'utc' => $result['instant']->toIso8601(),
                    'note' => $result['note'],
                    'offsetSeconds' => $result['offsetSeconds'],
                ],
            ]);
        } catch (TimeEngineException $e) {
            return response()->json([
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'details' => $e->details,
                ],
            ], 422);
        }
    }

    public function dayWindow(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'localDate' => ['required', 'date_format:Y-m-d'],
            'ianaId' => ['required', 'string', 'max:64'],
            'rolloverOffsetMinutes' => ['nullable', 'integer'],
        ]);

        $window = $time->businessDates()->localDayWindow(
            LocalDate::parse($data['localDate']),
            $data['ianaId'],
            $data['rolloverOffsetMinutes'] ?? null,
        );

        return response()->json([
            'data' => [
                'localDate' => $window['localDate'],
                'ianaId' => $window['ianaId'],
                'startUtc' => $window['start']->toIso8601(),
                'endUtc' => $window['end']->toIso8601(),
                'rolloverOffsetMinutes' => $window['rolloverOffsetMinutes'],
            ],
        ]);
    }

    public function observeDevice(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'devicePublicId' => ['required', 'string', 'max:64'],
            'rawTimestamp' => ['required', 'string', 'max:80'],
            'rawTimezone' => ['nullable', 'string', 'max:64'],
            'clockSkewMs' => ['nullable', 'integer'],
            'tenantId' => ['nullable', 'string', 'max:64'],
        ]);

        $row = $time->devices()->observe(
            $data['tenantId'] ?? $this->tenantKey($request),
            $data['devicePublicId'],
            $data['rawTimestamp'],
            $data['rawTimezone'] ?? null,
            $data['clockSkewMs'] ?? null,
        );

        return response()->json([
            'data' => [
                'publicId' => $row->public_id,
                'status' => $row->status,
                'confidence' => $row->confidence,
                'normalizedAtUtc' => $row->normalized_at_utc?->toIso8601String(),
                'quarantineReason' => $row->quarantine_reason,
            ],
        ], $row->status === 'QUARANTINED' ? 202 : 201);
    }

    public function captureSnapshot(Request $request, SharedTimeGateway $time): JsonResponse
    {
        $data = $request->validate([
            'tenantId' => ['nullable', 'string', 'max:64'],
            'branchId' => ['nullable', 'string', 'max:64'],
            'purpose' => ['nullable', 'string', 'max:24'],
            'displayIana' => ['nullable', 'string', 'max:64'],
        ]);

        $purpose = \App\Domain\Time\Enums\PolicyPurpose::tryFrom($data['purpose'] ?? 'OPERATIONAL')
            ?? \App\Domain\Time\Enums\PolicyPurpose::OPERATIONAL;

        return response()->json([
            'data' => $time->snapshots()->capture(
                $data['tenantId'] ?? $this->tenantKey($request),
                $data['branchId'] ?? null,
                $purpose,
                null,
                \App\Domain\Time\Enums\TimeSource::SERVER,
                $data['displayIana'] ?? Auth::user()?->presentation_timezone,
            ),
        ]);
    }

    private function tenantKey(Request $request): string
    {
        $user = Auth::user();
        if ($user && isset($user->business_id)) {
            return (string) $user->business_id;
        }

        return (string) ($request->header('X-Tenant-Key') ?: 'SYSTEM');
    }
}
