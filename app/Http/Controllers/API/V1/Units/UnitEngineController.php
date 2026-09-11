<?php

namespace App\Http\Controllers\API\V1\Units;

use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Services\ConversionEngine;
use App\Domain\Units\Services\UnitCatalogService;
use App\Domain\Units\ValueObjects\ConversionContext;
use App\Domain\Units\ValueObjects\Quantity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UnitEngineController extends Controller
{
    public function index(Request $request, UnitCatalogService $catalog): JsonResponse
    {
        $tenant = $this->tenantKey($request);
        $units = $catalog->search($tenant, $request->query('q'));

        return response()->json([
            'data' => collect($units)->map(fn ($u) => [
                'publicId' => $u->public_id,
                'code' => $u->code,
                'name' => $u->canonical_name,
                'symbol' => $u->symbol,
                'class' => $u->unit_class,
                'ucumCode' => $u->ucum_code,
                'status' => $u->status,
                'dimension' => $u->dimension_vector,
            ])->values(),
        ]);
    }

    public function show(string $unit, UnitCatalogService $catalog, Request $request): JsonResponse
    {
        $tenant = $this->tenantKey($request);
        $row = $catalog->findByPublicId($tenant, $unit);
        abort_unless($row, 404);

        return response()->json([
            'data' => [
                'publicId' => $row->public_id,
                'code' => $row->code,
                'name' => $row->canonical_name,
                'symbol' => $row->symbol,
                'class' => $row->unit_class,
                'ucumCode' => $row->ucum_code,
                'status' => $row->status,
                'dimension' => $row->dimension_vector,
            ],
        ]);
    }

    public function convert(Request $request, ConversionEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'regex:/^-?\d+(\.\d+)?$/'],
            'sourceUnitPublicId' => ['required', 'string', 'max:26'],
            'targetUnitPublicId' => ['required', 'string', 'max:26'],
            'effectiveAt' => ['nullable', 'date'],
            'context' => ['nullable', 'array'],
            'context.moduleCode' => ['nullable', 'string', 'max:64'],
            'context.analytePublicId' => ['nullable', 'string', 'max:64'],
            'context.itemPublicId' => ['nullable', 'string', 'max:64'],
            'context.methodPublicId' => ['nullable', 'string', 'max:64'],
            'context.procedurePublicId' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $ctx = $data['context'] ?? [];
            $result = $engine->convert(
                new Quantity($data['value'], $data['sourceUnitPublicId']),
                $data['targetUnitPublicId'],
                new ConversionContext(
                    tenantKey: $this->tenantKey($request),
                    moduleCode: $ctx['moduleCode'] ?? null,
                    analytePublicId: $ctx['analytePublicId'] ?? null,
                    itemPublicId: $ctx['itemPublicId'] ?? null,
                    methodPublicId: $ctx['methodPublicId'] ?? null,
                    procedurePublicId: $ctx['procedurePublicId'] ?? null,
                    effectiveAt: isset($data['effectiveAt'])
                        ? new \DateTimeImmutable($data['effectiveAt'])
                        : null,
                )
            );

            return response()->json([
                'data' => $result->toArray(),
                'meta' => ['correlationId' => (string) Str::ulid()],
            ]);
        } catch (ConversionException $e) {
            $status = match ($e->errorCode) {
                'UNKNOWN_UNIT' => 404,
                'INACTIVE_UNIT', 'AMBIGUOUS_RULE' => 409,
                default => 422,
            };

            return response()->json([
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'details' => $e->details,
                ],
            ], $status);
        }
    }

    protected function tenantKey(Request $request): string
    {
        $user = Auth::user();
        $businessId = $request->integer('business_id') ?: ($user?->business_id);

        return app(UnitCatalogService::class)->tenantKeyForBusiness($businessId ? (int) $businessId : null);
    }
}
